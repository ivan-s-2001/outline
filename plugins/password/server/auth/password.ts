import Router from "koa-router";
import { parseDomain } from "@shared/utils/domains";
import env from "@server/env";
import { Team, User } from "@server/models";
import { rateLimiter } from "@server/middlewares/rateLimiter";
import validate from "@server/middlewares/validate";
import type { APIContext } from "@server/types";
import { signIn } from "@server/utils/authentication";
import { verifyPassword } from "@server/utils/password";
import { RateLimiterStrategy } from "@server/utils/RateLimiter";
import * as T from "./schema";

const router = new Router();

const dummyPasswordHash =
  "scrypt$32768$8$1$6f75746c696e652d70617373776f72642d64756d6d79$1edacacb3903377f6d445466825b9b8604055a8412a9d9b16a6588209feee909275c32d113168748d7ff1908cf40cfe58bc1eac13e0e770f184257ddb4d7c963";

router.post(
  "password",
  rateLimiter(RateLimiterStrategy.TenPerHour),
  validate(T.PasswordSchema),
  async (ctx: APIContext<T.PasswordReq>) => {
    const { email, password, client } = ctx.input.body;
    const domain = parseDomain(ctx.request.hostname);

    let team: Team | null | undefined;
    if (!env.isCloudHosted) {
      team = await Team.findOne();
    } else if (domain.custom) {
      team = await Team.findOne({
        where: { domain: domain.host.toLowerCase() },
      });
    } else if (domain.teamSubdomain) {
      team = await Team.findOne({
        where: { subdomain: domain.teamSubdomain },
      });
    }

    if (!team) {
      ctx.redirect(
        `/?notice=auth-error&description=${encodeURIComponent(
          "Invalid email or password"
        )}`
      );
      return;
    }

    const user = await User.scope("withTeam").findOne({
      where: {
        teamId: team.id,
        email: email.trim().toLowerCase(),
      },
    });

    // Always run a password hash comparison, even when the account does not
    // exist, to reduce timing differences that could reveal valid addresses.
    const validPassword = await verifyPassword(
      password,
      user?.passwordHash ?? dummyPasswordHash
    );

    if (!user || !validPassword) {
      ctx.redirect(
        `/?notice=auth-error&description=${encodeURIComponent(
          "Invalid email or password"
        )}`
      );
      return;
    }

    await signIn(ctx, "password", {
      user,
      team,
      client,
      isNewTeam: false,
      isNewUser: false,
    });
  }
);

export default router;
