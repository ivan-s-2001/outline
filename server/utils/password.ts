import crypto from "node:crypto";

const algorithm = "scrypt";
const keyLength = 64;
const cost = 32768;
const blockSize = 8;
const parallelization = 1;
const maxmem = 128 * 1024 * 1024;

const deriveKey = (
  password: string,
  salt: Buffer,
  options = {
    N: cost,
    r: blockSize,
    p: parallelization,
    maxmem,
  }
) =>
  new Promise<Buffer>((resolve, reject) => {
    crypto.scrypt(password, salt, keyLength, options, (err, derivedKey) => {
      if (err) {
        reject(err);
      } else {
        resolve(derivedKey);
      }
    });
  });

export async function hashPassword(password: string): Promise<string> {
  const salt = crypto.randomBytes(16);
  const derivedKey = await deriveKey(password, salt);

  return [
    algorithm,
    cost,
    blockSize,
    parallelization,
    salt.toString("hex"),
    derivedKey.toString("hex"),
  ].join("$");
}

export async function verifyPassword(
  password: string,
  encodedHash: string
): Promise<boolean> {
  try {
    const [
      encodedAlgorithm,
      encodedCost,
      encodedBlockSize,
      encodedParallelization,
      encodedSalt,
      encodedKey,
    ] = encodedHash.split("$");

    if (
      encodedAlgorithm !== algorithm ||
      !encodedCost ||
      !encodedBlockSize ||
      !encodedParallelization ||
      !encodedSalt ||
      !encodedKey
    ) {
      return false;
    }

    const expected = Buffer.from(encodedKey, "hex");
    const actual = await deriveKey(password, Buffer.from(encodedSalt, "hex"), {
      N: Number(encodedCost),
      r: Number(encodedBlockSize),
      p: Number(encodedParallelization),
      maxmem,
    });

    return (
      expected.length === actual.length &&
      crypto.timingSafeEqual(expected, actual)
    );
  } catch (_err) {
    return false;
  }
}
