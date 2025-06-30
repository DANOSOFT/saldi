# Inventory Management REST API

This REST API provides endpoints for managing the inventory system including products, warehouses, product groups, and inventory status.

## Authentication

All endpoints require the following headers:
- `Authorization`: API key for authentication
- `x-saldiuser`: Username
- `x-db`: Database identifier

## Base URL
```
/restapi/endpoints/v1/
```

## Endpoints

### 1. Products (`/products/`)

#### GET `/products/`
Get all products or a specific product.

**Parameters:**
- `id` (optional): Product ID for single product retrieval
- `orderBy` (optional): Field to order by (id, varenr, beskrivelse, modtime)
- `orderDirection` (optional): ASC or DESC
- `field` (optional): Field to search by
- `value` (optional): Value to search for

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "varenr": "PROD001",
      "stregkode": "1234567890",
      "beskrivelse": "Product Description",
      "salgspris": 100.00,
      "kostpris": 50.00,
      "notes": "Additional notes",
      "serienr": "SN001",
      "min_lager": 10,
      "max_lager": 100,
      "location": "A1-01",
      "gruppe": 1,
      "netweight": 1.5,
      "netweightunit": "kg",
      "grossweight": 2.0,
      "grossweightunit": "kg",
      "length": 10.0,
      "width": 5.0,
      "height": 3.0
    }
  ]
}
```

#### POST `/products/`
Create a new product.

**Required fields:**
- `varenr`: Product number
- `beskrivelse`: Product description

**Optional fields:**
- `stregkode`: Barcode
- `salgspris`: Sales price
- `kostpris`: Cost price
- `notes`: Additional notes
- `serienr`: Serial number
- `min_lager`: Minimum stock level
- `max_lager`: Maximum stock level
- `location`: Storage location
- `gruppe`: Product group ID
- Size and weight properties

#### PUT `/products/`
Update an existing product.

**Required fields:**
- `id`: Product ID

**Optional fields:** Same as POST

#### DELETE `/products/`
Delete a product.

**Required fields:**
- `id`: Product ID

### 2. Warehouses (`/inventory/warehouses/`)

#### GET `/inventory/warehouses/`
Get all warehouses or a specific warehouse.

**Parameters:**
- `id` (optional): Warehouse ID
- `orderBy` (optional): Field to order by (kodenr, beskrivelse)
- `orderDirection` (optional): ASC or DESC
- `field` (optional): Field to search by
- `value` (optional): Value to search for
- `vare_id` (optional): Product ID for warehouse-product association

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "beskrivelse": "Main Warehouse",
      "nr": 1,
      "fiscal_year": 2024
    }
  ]
}
```

#### POST `/inventory/warehouses/`
Create a new warehouse.

**Required fields:**
- `beskrivelse`: Warehouse description
- `nr`: Warehouse number

**Optional fields:**
- `fiscal_year`: Fiscal year

#### PUT `/inventory/warehouses/`
Update an existing warehouse.

**Required fields:**
- `id`: Warehouse ID

#### DELETE `/inventory/warehouses/`
Delete a warehouse.

**Required fields:**
- `id`: Warehouse ID

### 3. Product Groups (`/inventory/groups/`)

#### GET `/inventory/groups/`
Get all product groups or a specific product group.

**Parameters:**
- `id` (optional): Group ID
- `orderBy` (optional): Field to order by
- `orderDirection` (optional): ASC or DESC
- `field` (optional): Field to search by
- `value` (optional): Value to search for

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "kodenr": "GRP001",
      "beskrivelse": "Electronics",
      "fiscal_year": 2024,
      "omv_bet": true,
      "moms_fri": false,
      "lager": true,
      "batch": false,
      "operation": false,
      "buy_account": 1000,
      "sell_account": 2000,
      "buy_eu_account": 1100,
      "sell_eu_account": 2100
    }
  ]
}
```

#### POST `/inventory/groups/`
Create a new product group.

**Required fields:**
- `kodenr`: Group code
- `beskrivelse`: Group description

**Optional fields:**
- `fiscal_year`: Fiscal year
- Boolean options: `omv_bet`, `moms_fri`, `lager`, `batch`, `operation`
- Account fields: `buy_account`, `sell_account`, etc.

#### PUT `/inventory/groups/`
Update an existing product group.

**Required fields:**
- `id`: Group ID

#### DELETE `/inventory/groups/`
Delete a product group.

**Required fields:**
- `id`: Group ID

### 4. Inventory Status (`/inventory/status/`)

#### GET `/inventory/status/`
Get inventory status for all items or specific items.

**Parameters:**
- `id` (optional): Status record ID
- `orderBy` (optional): Field to order by
- `orderDirection` (optional): ASC or DESC
- `field` (optional): Field to search by
- `value` (optional): Value to search for
- `lager_nr` (optional): Warehouse number to get inventory for specific warehouse

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "lager": 1,
      "vare_id": 123,
      "beholdning": 50.0,
      "lok": "A1-01",
      "variant_id": 0
    }
  ]
}
```

#### POST `/inventory/status/`
Create a new inventory status record.

**Required fields:**
- `lager`: Warehouse ID
- `vare_id`: Product ID
- `beholdning`: Quantity

**Optional fields:**
- `lok`: Location within warehouse
- `variant_id`: Product variant ID

#### PUT `/inventory/status/`
Update an existing inventory status record.

**Required fields:**
- `id`: Status record ID

#### PATCH `/inventory/status/`
Perform inventory adjustments.

**Actions:**
1. Adjust quantity by amount:
```json
{
  "action": "adjust_quantity",
  "id": 1,
  "amount": 10
}
```

2. Set specific quantity:
```json
{
  "action": "set_quantity",
  "id": 1,
  "quantity": 100
}
```

#### DELETE `/inventory/status/`
Clear inventory status (sets quantity to 0).

**Required fields:**
- `id`: Status record ID

## Error Responses

All endpoints return standardized error responses:

```json
{
  "success": false,
  "message": "Error description"
}
```

Common HTTP status codes:
- `200`: Success
- `201`: Created
- `400`: Bad Request
- `401`: Unauthorized
- `404`: Not Found
- `500`: Internal Server Error

## Usage Examples

### Create a Product
```bash
curl -X POST "https://yourdomain.com/restapi/endpoints/v1/products/" \
  -H "Authorization: your-api-key" \
  -H "x-saldiuser: username" \
  -H "x-db: database" \
  -H "Content-Type: application/json" \
  -d '{
    "varenr": "PROD001",
    "beskrivelse": "Test Product",
    "salgspris": 100.00,
    "kostpris": 50.00
  }'
```

### Get All Products
```bash
curl -X GET "https://yourdomain.com/restapi/endpoints/v1/products/" \
  -H "Authorization: your-api-key" \
  -H "x-saldiuser: username" \
  -H "x-db: database"
```

### Adjust Inventory
```bash
curl -X PATCH "https://yourdomain.com/restapi/endpoints/v1/inventory/status/" \
  -H "Authorization: your-api-key" \
  -H "x-saldiuser: username" \
  -H "x-db: database" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "adjust_quantity",
    "id": 1,
    "amount": 10
  }'
```
