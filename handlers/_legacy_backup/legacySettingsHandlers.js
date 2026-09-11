const { ipcMain } = require("electron");

// 'update-profile' still writes to the local Users table — split out from
// settingsHandlers.js (now fully API-backed and unconditional) purely
// because this one handler genuinely still needs a local `db` connection.
module.exports = function setupLegacySettingsHandlers(db, store) {
  ipcMain.handle("update-profile", async (event, profileData) => {
    try {
      const user = store.get("user");

      // Check if non-admin is trying to change organization name
      if (user.role !== "admin" && profileData.organization_name !== user.organization_name) {
        return {
          success: false,
          message: "Only administrators can change organization name",
        };
      }

      const [existingUsers] = await db.query(
        "SELECT id FROM Users WHERE username = ? AND id != ?",
        [profileData.username, profileData.userId],
      );

      if (existingUsers.length > 0) {
        return {
          success: false,
          message: "Username already taken by another user",
        };
      }

      // For non-admin users, preserve their existing organization_name (they can't change it)
      // For admin users, allow updating organization_name
      const organizationNameToUpdate = user.role === "admin"
        ? profileData.organization_name
        : user.organization_name;

      await db.query(
        "UPDATE Users SET name = ?, username = ?, organization_name = ? WHERE id = ?",
        [profileData.name, profileData.username, organizationNameToUpdate, profileData.userId],
      );

      const [updatedUser] = await db.query("SELECT * FROM Users WHERE id = ?", [profileData.userId]);
      if (updatedUser.length > 0) {
        store.set("user", updatedUser[0]);
      }

      return {
        success: true,
        message: "Profile updated successfully",
      };
    } catch (error) {
      console.error("Error updating profile:", error);
      return {
        success: false,
        message: error.message,
      };
    }
  });
};
