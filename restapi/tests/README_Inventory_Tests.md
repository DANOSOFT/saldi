# Inventory API Test Suite

This directory contains comprehensive tests for all inventory management API endpoints.

## Test Files

### Individual Endpoint Tests
- **`InventoryProductsEndpointTest.php`** - Tests for products endpoint (`/v1/products/`)
- **`InventoryWarehousesEndpointTest.php`** - Tests for warehouses endpoint (`/v1/inventory/warehouses/`)
- **`InventoryProductGroupsEndpointTest.php`** - Tests for product groups endpoint (`/v1/inventory/groups/`)
- **`InventoryStatusEndpointTest.php`** - Tests for inventory status endpoint (`/v1/inventory/status/`)

### Test Suite Runner
- **`InventoryTestSuite.php`** - Master test runner for all inventory tests

## Setup

### 1. Configure API Credentials

Before running tests, update the following values in each test file:

```php
$this->baseUrl = 'https://yourdomain.com/restapi/endpoints/v1/...';

$this->headers = [
    'Content-Type: application/json',
    'Authorization: YOUR_API_KEY_HERE',        // ← Update this
    'X-SaldiUser: YOUR_USERNAME_HERE',         // ← Update this
    'X-DB: YOUR_DATABASE_HERE'                 // ← Update this
];
```

### 2. Verify Endpoint URLs

Ensure the `baseUrl` in each test matches your server configuration.

## Running Tests

### Run All Inventory Tests
```bash
php InventoryTestSuite.php
```

### Run Individual Test Files
```bash
php InventoryProductsEndpointTest.php
php InventoryWarehousesEndpointTest.php
php InventoryProductGroupsEndpointTest.php
php InventoryStatusEndpointTest.php
```

### Health Check Only
```bash
php InventoryTestSuite.php health
```

## Test Coverage

### Products Endpoint Tests
- ✅ Create basic product
- ✅ Create product with full data (size, weight, etc.)
- ✅ Get all products
- ✅ Get single product
- ✅ Search products by field
- ✅ Order products by various fields
- ✅ Update product (full and partial)
- ✅ Update product size/weight data
- ✅ Delete product
- ✅ Validation (missing fields, duplicates)
- ✅ Error handling (non-existent products)

### Warehouses Endpoint Tests
- ✅ Create basic warehouse
- ✅ Create warehouse with fiscal year
- ✅ Get all warehouses
- ✅ Get single warehouse
- ✅ Search warehouses by field
- ✅ Order warehouses
- ✅ Update warehouse
- ✅ Delete warehouse
- ✅ Validation (missing fields, duplicates)
- ✅ Error handling (non-existent warehouses)

### Product Groups Endpoint Tests
- ✅ Create basic product group
- ✅ Create product group with full data (accounts, booleans)
- ✅ Get all product groups
- ✅ Get single product group
- ✅ Search product groups
- ✅ Order product groups
- ✅ Update product group
- ✅ Update boolean options
- ✅ Update account settings
- ✅ Delete product group
- ✅ Validation (missing fields, duplicates)
- ✅ Error handling (non-existent groups)

### Inventory Status Endpoint Tests
- ✅ Create inventory status
- ✅ Create inventory status with location
- ✅ Get all inventory status
- ✅ Get single inventory status
- ✅ Get warehouse-specific inventory
- ✅ Search inventory status
- ✅ Order inventory status
- ✅ Update inventory status
- ✅ Adjust quantity (positive/negative)
- ✅ Set absolute quantity
- ✅ Clear inventory status
- ✅ Validation (missing fields)
- ✅ Error handling (non-existent records)

## Test Features

### Automatic Cleanup
All tests automatically clean up created test data to avoid cluttering the database.

### Comprehensive Validation
Tests verify:
- Required field validation
- Data type validation
- Business logic validation
- Duplicate prevention
- Error handling

### Real API Testing
Tests use actual HTTP requests to verify:
- Authentication
- Request/response format
- HTTP status codes
- JSON structure
- Error messages

### Search and Filtering
Tests verify:
- Field-based searching
- Result ordering
- Parameter validation
- Query string handling

## Sample Test Output

```
=== Inventory Products API Endpoint Tests ===

Testing: Create Basic Product
✓ Product created successfully with ID: 123
✓ Product number correctly set

Testing: Get All Products
✓ Retrieved 15 products
✓ Created product ID 123 found in list

Testing: Update Product
✓ Product updated successfully
✓ Product description correctly updated
✓ Product price correctly updated

=== Test Summary ===
All Products API tests completed successfully!

=== Cleanup ===
✓ Cleaned up product ID: 123
```

## Troubleshooting

### Common Issues

1. **Authentication Errors**
   - Verify API key, username, and database in headers
   - Check if API access is enabled for the database

2. **Connection Errors**
   - Verify base URL is correct
   - Check SSL certificate settings
   - Ensure server is accessible

3. **Permission Errors**
   - Verify user has CRUD permissions
   - Check database access rights

4. **Test Data Conflicts**
   - Ensure test data doesn't conflict with existing records
   - Check unique constraints (product numbers, etc.)

### Debug Mode

To enable verbose output for debugging, you can modify the test files to include more detailed logging:

```php
// Add this to any test method for debugging
echo "Request URL: $url\n";
echo "Request Data: " . json_encode($data) . "\n";
echo "Response: " . $response . "\n";
```

## Integration with CI/CD

These tests can be integrated into continuous integration pipelines:

```bash
# Example CI script
php InventoryTestSuite.php health
if [ $? -eq 0 ]; then
    php InventoryTestSuite.php
fi
```

## Test Data

Tests use predictable test data patterns:
- Product numbers: `TEST-PROD-{timestamp}`
- Group codes: `TEST-GRP-{timestamp}`
- Descriptions: Include "Test" prefix for easy identification

This ensures test data is easily identifiable and doesn't interfere with production data.
