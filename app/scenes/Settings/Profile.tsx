import { observer } from "mobx-react";
import { ProfileIcon } from "outline-icons";
import * as React from "react";
import { Trans, useTranslation } from "react-i18next";
import { toast } from "sonner";
import { errToString } from "@shared/utils/error";
import Button from "~/components/Button";
import Heading from "~/components/Heading";
import Input from "~/components/Input";
import Scene from "~/components/Scene";
import Text from "~/components/Text";
import { UserChangeEmailDialog } from "~/components/UserDialogs";
import env from "~/env";
import useCurrentUser from "~/hooks/useCurrentUser";
import useStores from "~/hooks/useStores";
import { client } from "~/utils/ApiClient";
import { UserValidation } from "@shared/validations";
import ImageInput from "./components/ImageInput";
import SettingRow from "./components/SettingRow";

const Profile = () => {
  const user = useCurrentUser();
  const { dialogs } = useStores();
  const form = React.useRef<HTMLFormElement>(null);
  const [name, setName] = React.useState<string>(user.name);
  const [currentPassword, setCurrentPassword] = React.useState("");
  const [newPassword, setNewPassword] = React.useState("");
  const [confirmPassword, setConfirmPassword] = React.useState("");
  const [isPasswordSaving, setPasswordSaving] = React.useState(false);
  const { t } = useTranslation();

  const handleSubmit = async (ev: React.SyntheticEvent) => {
    ev.preventDefault();

    try {
      await user.save({ name });
      toast.success(t("Profile saved"));
    } catch (err) {
      toast.error(errToString(err));
    }
  };

  const handlePasswordSubmit = async (ev: React.SyntheticEvent) => {
    ev.preventDefault();

    if (newPassword !== confirmPassword) {
      toast.error(t("Passwords do not match"));
      return;
    }

    setPasswordSaving(true);
    try {
      await client.post("/users.updatePassword", {
        currentPassword,
        password: newPassword,
      });
      setCurrentPassword("");
      setNewPassword("");
      setConfirmPassword("");
      toast.success(t("Password changed"));
    } catch (err) {
      toast.error(errToString(err));
    } finally {
      setPasswordSaving(false);
    }
  };

  const handleChangeEmail = () => {
    dialogs.openModal({
      title: t("Change email"),
      content: (
        <UserChangeEmailDialog user={user} onSubmit={dialogs.closeAllModals} />
      ),
    });
  };

  const handleNameChange = (ev: React.ChangeEvent<HTMLInputElement>) => {
    setName(ev.target.value);
  };

  const handleAvatarChange = async (avatarUrl: string) => {
    await user.save({ avatarUrl });
    toast.success(t("Profile picture updated"));
  };

  const handleAvatarError = (error: string | null | undefined) => {
    toast.error(error || t("Unable to upload new profile picture"));
  };

  const isValid = form.current?.checkValidity();
  const { isSaving } = user;

  return (
    <Scene title={t("Profile")} icon={<ProfileIcon />}>
      <Heading>{t("Profile")}</Heading>
      <Text as="p" type="secondary">
        <Trans>Manage how you appear to other members of the workspace.</Trans>
      </Text>

      <form onSubmit={handleSubmit} ref={form}>
        <SettingRow
          label={t("Photo")}
          name="avatarUrl"
          description={t("Choose a photo or image to represent yourself.")}
        >
          <ImageInput
            alt={t("Profile picture")}
            onSuccess={handleAvatarChange}
            onError={handleAvatarError}
            model={user}
          />
        </SettingRow>
        <SettingRow
          border={env.EMAIL_ENABLED}
          label={t("Name")}
          name="name"
          description={t(
            "This could be your real name, or a nickname — however you’d like people to refer to you."
          )}
        >
          <Input
            id="name"
            autoComplete="name"
            value={name}
            onChange={handleNameChange}
            maxLength={UserValidation.maxNameLength}
            showCharacterCount
            required
          />
        </SettingRow>

        {env.EMAIL_ENABLED && (
          <SettingRow border={false} label={t("Email address")} name="email">
            <Input
              type="email"
              value={user.email}
              readOnly
              onClick={handleChangeEmail}
            />
          </SettingRow>
        )}

        <Button type="submit" disabled={isSaving || !isValid}>
          {isSaving ? `${t("Saving")}…` : t("Save")}
        </Button>
      </form>

      <Heading as="h2">{t("Change password")}</Heading>
      <Text as="p" type="secondary">
        {t("Use at least 12 characters.")}
      </Text>
      <form onSubmit={handlePasswordSubmit}>
        <SettingRow
          label={t("Current password")}
          name="currentPassword"
          description=""
        >
          <Input
            id="currentPassword"
            type="password"
            autoComplete="current-password"
            value={currentPassword}
            onChange={(ev) => setCurrentPassword(ev.target.value)}
            maxLength={128}
            required
          />
        </SettingRow>
        <SettingRow label={t("New password")} name="newPassword" description="">
          <Input
            id="newPassword"
            type="password"
            autoComplete="new-password"
            value={newPassword}
            onChange={(ev) => setNewPassword(ev.target.value)}
            minLength={12}
            maxLength={128}
            required
          />
        </SettingRow>
        <SettingRow
          border={false}
          label={t("Confirm password")}
          name="confirmPassword"
          description=""
        >
          <Input
            id="confirmPassword"
            type="password"
            autoComplete="new-password"
            value={confirmPassword}
            onChange={(ev) => setConfirmPassword(ev.target.value)}
            minLength={12}
            maxLength={128}
            required
          />
        </SettingRow>
        <Button
          type="submit"
          disabled={
            isPasswordSaving ||
            !currentPassword ||
            !newPassword ||
            newPassword !== confirmPassword
          }
        >
          {isPasswordSaving ? `${t("Saving")}…` : t("Change password")}
        </Button>
      </form>
    </Scene>
  );
};

export default observer(Profile);
