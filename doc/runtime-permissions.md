# Shared runtime files

Online/login and database logs use `includes/sharedFile.php`. Files use `0660`;
directories use `02770` (setgid), so new entries inherit the shared group. The
default group is `www-data`. Set `SALDI_SHARED_FILE_GROUP` consistently for PHP
workers and CLI jobs to use a dedicated group instead. PHP's POSIX extension is
required. Only trusted application accounts belong to this group.

The helpers validate every parent component before changing permissions or
opening a file, including before a `w` open can truncate it. Symlinks, special
files, parents writable by others, and parents writable by unrelated groups are
refused. Sticky system ancestors such as `/tmp` are allowed only above an entry
owned by root or the current account; the immediate parent is never allowed to
be writable by others. Directory owners and members of the shared group are
trusted. Deploy the application under trusted owners and restrict write access
to its parent directories as well.

## One-time setup and migration

An unprivileged web worker cannot chmod a CLI user's `0600`/`0644` logs. Deploying
PHP code alone cannot repair that situation. Before enabling the helpers:

1. Stop web workers and CLI jobs that write runtime files. Check the runtime tree
   for unexpected entries; the migration refuses symlinks and special files.
2. Have an administrator add each CLI account to the shared group, for example
   `sudo usermod -a -G www-data YOUR_CLI_ACCOUNT`. Ensure the web account also
   belongs to this group. Start a new login session for group membership changes.
3. Remove write access for unrelated accounts from application ancestors,
   including the application directory itself. Use `chmod o-w` on each affected
   ancestor and ensure any group with write access is the trusted shared group.
   The migration refuses unsafe ancestors rather than trying to repair them.
4. Run the migration as an administrator, using the actual installation path:

   ```sh
   sudo php tools/migrate_shared_runtime.php /absolute/path/to/saldi/temp
   ```

   For a dedicated group, pass it explicitly to the administrator's process:

   ```sh
   sudo env SALDI_SHARED_FILE_GROUP=saldi-runtime php tools/migrate_shared_runtime.php /absolute/path/to/saldi/temp
   ```

   This migrates existing files to the shared group and `0660`, and directories
   to root ownership, the shared group and `02770`, preserving file contents and
   file owners. The entire `temp` tree, including tenant directories, is covered.
   A failed migration exits nonzero; resolve the reported entry before restarting.
5. Restart web workers and CLI jobs, with the same shared-group configuration.
   Verify that a CLI-created log can be appended by the web account and that an
   unrelated account cannot read it or replace entries in its parent directory.

The Docker entrypoint applies the directory permissions and runs this migration
as root before starting Apache. On other installations, run the steps above
explicitly before deployment.

Existing files already using the correct group and mode work across owners
without chmod. A restrictive foreign-owned file encountered later is refused
with an actionable migration warning and a `false` return, without truncation
or privilege escalation. Repeat the administrator migration while writers are
stopped. There is no fallback to access for every local user.

Other legacy temporary-file writers still need to be migrated to these helpers
when changed; a setgid directory supplies group inheritance, but each writer's
creation mask still determines group write access.
