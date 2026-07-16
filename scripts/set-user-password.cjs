"use strict";

const crypto = require("node:crypto");
const fs = require("node:fs");
const path = require("node:path");
const { Client } = require("pg");

function loadEnvFile(filename) {
  if (!fs.existsSync(filename)) {
    return;
  }

  for (const line of fs.readFileSync(filename, "utf8").split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith("#")) {
      continue;
    }

    const separator = trimmed.indexOf("=");
    if (separator === -1) {
      continue;
    }

    const key = trimmed.slice(0, separator).trim();
    let value = trimmed.slice(separator + 1).trim();

    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }

    process.env[key] = value;
  }
}

function loadEnvironment() {
  const cwd = process.cwd();
  loadEnvFile(path.join(cwd, ".env"));
  const environment = process.env.NODE_ENV || "development";
  loadEnvFile(path.join(cwd, `.env.${environment}`));
  loadEnvFile(path.join(cwd, ".env.local"));
}

function deriveKey(password, salt) {
  return new Promise((resolve, reject) => {
    crypto.scrypt(
      password,
      salt,
      64,
      {
        N: 32768,
        r: 8,
        p: 1,
        maxmem: 128 * 1024 * 1024,
      },
      (err, derivedKey) => {
        if (err) {
          reject(err);
        } else {
          resolve(derivedKey);
        }
      }
    );
  });
}

async function hashPassword(password) {
  const salt = crypto.randomBytes(16);
  const key = await deriveKey(password, salt);
  return `scrypt$32768$8$1$${salt.toString("hex")}$${key.toString("hex")}`;
}

async function main() {
  loadEnvironment();

  const [emailArg, password] = process.argv.slice(2);
  const email = emailArg?.trim().toLowerCase();

  if (!email || !password) {
    console.error(
      'Usage: node scripts/set-user-password.cjs "user@example.com" "Strong password"'
    );
    process.exit(1);
  }

  if (password.length < 12 || password.length > 128) {
    console.error("Password length must be between 12 and 128 characters.");
    process.exit(1);
  }

  if (!process.env.DATABASE_URL) {
    console.error("DATABASE_URL was not found in .env files.");
    process.exit(1);
  }

  const client = new Client({
    connectionString: process.env.DATABASE_URL,
    ssl:
      process.env.PGSSLMODE === "disable"
        ? false
        : process.env.PGSSLMODE
          ? { rejectUnauthorized: false }
          : undefined,
  });

  await client.connect();

  try {
    const hash = await hashPassword(password);
    const result = await client.query(
      `
        UPDATE users
        SET "passwordHash" = $1,
            "passwordChangedAt" = NOW()
        WHERE LOWER(email) = $2
          AND "deletedAt" IS NULL
        RETURNING id, name, email
      `,
      [hash, email]
    );

    if (!result.rowCount) {
      console.error(`User not found: ${email}`);
      process.exitCode = 2;
      return;
    }

    const user = result.rows[0];
    console.log(`Password updated for ${user.name} <${user.email}>.`);
  } finally {
    await client.end();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
