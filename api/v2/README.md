# SALDI API v2

This is the new version of the SALDI API that provides a more secure and efficient way to access SALDI data.

## Authentication

All API requests must include an API key in the `X-API-Key` header:

```
X-API-Key: your-api-key-here
```

## Endpoints

### Addresses

#### Get All Addresses
```
GET /api/v2/addresses.php
```

#### Get Single Address
```
GET /api/v2/addresses.php?id=123
```

#### Create Address
```
POST /api/v2/addresses.php
Content-Type: application/json

{
    "firmanavn": "Company Name",
    "addr1": "Address Line 1",
    "postnr": "1234",
    "bynavn": "City",
    ...
}
```

#### Update Address
```
PUT /api/v2/addresses.php?id=123
Content-Type: application/json

{
    "firmanavn": "New Company Name",
    "addr1": "New Address Line 1",
    ...
}
```

#### Delete Address
```
DELETE /api/v2/addresses.php?id=123
```

## Response Format

All responses are in JSON format:

### Success Response
```json
{
    "id": 123,
    "message": "Operation successful"
}
```

### Error Response
```json
{
    "error": true,
    "message": "Error description"
}
```

## Error Codes

- 200: Success
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 404: Not Found
- 405: Method Not Allowed
- 500: Internal Server Error

## Database Setup

Before using the API, you need to set up the API keys table in your master database. Run the SQL script in `includes/api_keys.sql` to create the necessary table and functions.

## Security Notes

1. Always use HTTPS in production
2. Keep your API keys secure
3. Rotate API keys periodically
4. Monitor API usage for suspicious activity 