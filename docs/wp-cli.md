# WP-CLI Commands Documentation

The Custom Product Configurator includes several WP-CLI commands to help manage the plugin from the command line.

## Available Commands

### System Tests

Run system tests to verify plugin functionality:

```bash
# Run all tests
wp cpc test

# Run specific category of tests
wp cpc test --category=system

# Output in different formats
wp cpc test --format=json
wp cpc test --format=csv
wp cpc test --format=yaml

# Show detailed output
wp cpc test --verbose
```

### Sample Product Management

Create and manage sample products:

```bash
# Create sample product
wp cpc create-sample-product

# Force create new sample product
wp cpc create-sample-product --force

# Create with specific author
wp cpc create-sample-product --author=admin@example.com
```

### Log Management

Manage plugin logs:

```bash
# Clear logs with confirmation
wp cpc clear-logs

# Clear logs without confirmation
wp cpc clear-logs --yes
```

### Database Management

Manage plugin database tables:

```bash
# Recreate tables with confirmation
wp cpc recreate-tables

# Drop and recreate tables without confirmation
wp cpc recreate-tables --yes --drop-existing
```

### Configuration Export

Export plugin configuration:

```bash
# Export as JSON
wp cpc export-config

# Export as YAML
wp cpc export-config --format=yaml

# Export to file
wp cpc export-config --file=config.json
```

## Command Details

### `wp cpc test`

Run system tests to verify plugin functionality.

Options:
- `--format=<format>`: Output format (table, json, csv, yaml)
- `--category=<category>`: Test category to run (system, integration, database)
- `--verbose`: Show detailed test output

Example Output:
```
+------------------+--------+-----------------+
| Test             | Status | Message         |
+------------------+--------+-----------------+
| PHP Version      | PASS   |                |
| WordPress Version| PASS   |                |
| Database Tables  | FAIL   | Tables missing |
+------------------+--------+-----------------+
```

### `wp cpc create-sample-product`

Create a sample product with all configurations.

Options:
- `--force`: Override existing sample product
- `--author=<user>`: Set the product author (user ID, email, or login)

Example:
```bash
# Create sample product with specific author
wp cpc create-sample-product --author=brieana.davis@example.com
```

### `wp cpc clear-logs`

Clear plugin logs.

Options:
- `--yes`: Skip confirmation

Example:
```bash
# Clear logs without confirmation
wp cpc clear-logs --yes
```

### `wp cpc recreate-tables`

Recreate plugin database tables.

Options:
- `--yes`: Skip confirmation
- `--drop-existing`: Drop existing tables before recreation

Example:
```bash
# Drop and recreate tables
wp cpc recreate-tables --drop-existing --yes
```

### `wp cpc export-config`

Export plugin configuration.

Options:
- `--format=<format>`: Output format (json, yaml)
- `--file=<file>`: Write output to a file

Example:
```bash
# Export configuration as YAML to file
wp cpc export-config --format=yaml --file=config.yaml
```

## Development

### Adding Custom Commands

You can add custom commands by extending the CLI_Commands class:

```php
add_filter('cpc_cli_commands', function($commands) {
    $commands['my-command'] = array(
        'callback' => function($args, $assoc_args) {
            // Command implementation
        },
        'synopsis' => array(
            array(
                'type'     => 'assoc',
                'name'     => 'format',
                'optional' => true,
            ),
        )
    );
    return $commands;
});
```

### Command Hooks

Available hooks for extending command functionality:

```php
// Before test command runs
add_action('cpc_cli_before_test', function($args, $assoc_args) {
    // Your code here
});

// After test command completes
add_action('cpc_cli_after_test', function($results) {
    // Your code here
});

// Filter test results
add_filter('cpc_cli_test_results', function($results) {
    // Modify results
    return $results;
});
```

## Best Practices

1. **Error Handling**
   - Use `WP_CLI::error()` for fatal errors
   - Use `WP_CLI::warning()` for non-fatal issues
   - Use `WP_CLI::debug()` for debug information

2. **Progress Feedback**
   - Use `WP_CLI::log()` for general output
   - Use `WP_CLI::success()` for success messages
   - Use progress bars for long operations

3. **Data Formatting**
   - Support multiple output formats
   - Use consistent data structures
   - Format data appropriately for each output type

4. **Performance**
   - Use batch processing for large operations
   - Implement timeouts for long-running commands
   - Cache results when appropriate

## Examples

### Running Tests with Custom Format

```bash
# Run tests and output as JSON
wp cpc test --format=json > test-results.json

# Run specific category with verbose output
wp cpc test --category=database --verbose

# Export test results to file
wp cpc test --format=csv --file=test-results.csv
```

### Managing Sample Products

```bash
# Create sample product and export ID
wp cpc create-sample-product --porcelain

# Create multiple sample products
for i in {1..5}; do
  wp cpc create-sample-product --force
done
```

### Database Management

```bash
# Backup before recreating tables
wp db export backup.sql
wp cpc recreate-tables --yes

# Verify tables after recreation
wp db tables "wp_*product*" --format=csv
```

## Support

For additional support:
- Run `wp help cpc <command>` for detailed help
- Check the error logs (`wp cpc clear-logs`)
- Submit issues with command output
- Contact support with debug information (`wp cpc test --verbose`)
