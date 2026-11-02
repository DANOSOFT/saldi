# Shared runtime files

Online/login and database logs use `includes/sharedFile.php`. Files use `0660`;
directories use `02770` (setgid), so new entries inherit the shared group. The
group is `balances` when that group exists and `www-data` is a member of it (the
setup in `INSTALLATION.md`), and `www-data` otherwise. Set
`SALDI_SHARED_FILE_GROUP` consistently for PHP workers and CLI jobs to use a
dedicated group instead. PHP's POSIX extension is required. Only trusted
application accounts belong to this group.

The helpers validate every parent component before changing permissions or
opening a file, including before a `w` open can truncate it. Special files,
parents writable by others, and parents writable by unrelated groups are refused.
Symlinked installations work: a symlink in the path is followed when the
directory holding it passed the check (so only trusted accounts can replace it),
and the directories it points to are checked the same way. A log file or tenant
directory that is itself a symlink is used without changing its permissions.
Sticky system ancestors such as `/tmp` are allowed only above an entry
owned by root or the current account; the immediate parent is never allowed to
be writable by others. Directory owners and members of the shared group are
trusted. Deploy the application under trusted owners and restrict write access
to its parent directories as well.

## One-time setup and migration

An unprivileged web worker cannot chmod a CLI user's `0600`/`0644` logs. Deploying
PHP code alone cannot repair that situation. Before enabling the helpers:

1. Stop web workers and CLI jobs that write runtime files. Check the runtime tree
   for unexpected entries; the migration refuses symlinks and special files
   inside it. A symlinked `temp` directory itself is migrated at its target.
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
without chmod. A foreign-owned file the caller cannot repair raises an actionable
migration warning and is then written only if its current permissions allow it;
there is no privilege escalation. Repeat the administrator migration while
writers are stopped. There is no fallback to access for every local user.

Until an installation is migrated (an untrusted parent such as a `777` `temp`
directory, a symlink pointing into one, or an unknown group), the helpers keep the plain
`fopen`/`mkdir` (`0770`) writes used before them and change no permissions through
that chain. Each distinct warning is raised once per request, so per-query logs
do not flood the error log. Overwrites open the file without truncating it,
compare the opened file with the path, and only then truncate it.

Other legacy temporary-file writers still need to be migrated to these helpers
when changed; a setgid directory supplies group inheritance, but each writer's
creation mask still determines group write access.
