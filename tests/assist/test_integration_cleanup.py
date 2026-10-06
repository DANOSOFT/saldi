"""Failure-injection checks for cleanup of disposable integration resources."""
import contextlib
import io
import unittest

from run_records_integration import cleanup_resources


class CleanupTests(unittest.TestCase):
    """One failed cleanup must not strand the remaining databases or reader."""

    def test_container_and_database_failures_do_not_skip_remaining_resources(self):
        """Attempt every resource, force-disconnect DB sessions, and redact errors."""
        attempted = []

        def remove_container(args):
            """Simulate a Docker failure whose detail must not reach stderr."""
            attempted.append("container")
            raise RuntimeError("sensitive subprocess detail")

        def sql(database, statement):
            """Leave the first drop failing while recording all later attempts."""
            self.assertEqual(database, "postgres")
            attempted.append(statement)
            if statement == "DROP DATABASE fixture_tenant WITH (FORCE);":
                raise RuntimeError("sensitive database detail")

        output = io.StringIO()
        with contextlib.redirect_stderr(output):
            failures = cleanup_resources("fixture_container", ["fixture_master", "fixture_tenant"], "fixture_reader", sql, remove_container)
        self.assertEqual(failures, 2)
        self.assertEqual(attempted, ["container", "DROP DATABASE fixture_tenant WITH (FORCE);",
                                    "DROP DATABASE fixture_master WITH (FORCE);", "DROP ROLE fixture_reader;"])
        self.assertNotIn("sensitive", output.getvalue())
        self.assertEqual(output.getvalue().count("cleanup step failed: RuntimeError"), 2)

    def test_only_created_resources_are_cleaned(self):
        """An early provisioning failure leaves no role/container to remove."""
        statements = []
        failures = cleanup_resources(None, ["fixture_master"], None,
                                     lambda database, statement: statements.append(statement))
        self.assertEqual(failures, 0)
        self.assertEqual(statements, ["DROP DATABASE fixture_master WITH (FORCE);"])


if __name__ == "__main__":
    unittest.main()
