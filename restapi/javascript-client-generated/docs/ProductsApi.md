# PblmRestApi.ProductsApi

All URIs are relative to *https://virtserver.swaggerhub.com/saldi-dc1/swagger/1.0.0*

Method | HTTP request | Description
------------- | ------------- | -------------
[**productsGet**](ProductsApi.md#productsGet) | **GET** /products | Get all products
[**productsIdDelete**](ProductsApi.md#productsIdDelete) | **DELETE** /products/{id} | Delete a product
[**productsIdGet**](ProductsApi.md#productsIdGet) | **GET** /products/{id} | Get a specific product
[**productsIdPut**](ProductsApi.md#productsIdPut) | **PUT** /products/{id} | Update a product
[**productsPost**](ProductsApi.md#productsPost) | **POST** /products | Create a new product

<a name="productsGet"></a>
# **productsGet**
> ProductListResponse productsGet(xDb, xSaldiuser, xApikey)

Get all products

### Example
```javascript
import {PblmRestApi} from 'pblm_rest_api';
let defaultClient = PblmRestApi.ApiClient.instance;

// Configure API key authorization: ApiKeyAuth
let ApiKeyAuth = defaultClient.authentications['ApiKeyAuth'];
ApiKeyAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//ApiKeyAuth.apiKeyPrefix = 'Token';

// Configure API key authorization: DatabaseAuth
let DatabaseAuth = defaultClient.authentications['DatabaseAuth'];
DatabaseAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//DatabaseAuth.apiKeyPrefix = 'Token';

// Configure API key authorization: UserAuth
let UserAuth = defaultClient.authentications['UserAuth'];
UserAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//UserAuth.apiKeyPrefix = 'Token';

let apiInstance = new PblmRestApi.ProductsApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication

apiInstance.productsGet(xDb, xSaldiuser, xApikey, (error, data, response) => {
  if (error) {
    console.error(error);
  } else {
    console.log('API called successfully. Returned data: ' + data);
  }
});
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **xDb** | **String**| Database identifier | 
 **xSaldiuser** | **String**| User identifier | 
 **xApikey** | **String**| API key for authentication | 

### Return type

[**ProductListResponse**](ProductListResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

<a name="productsIdDelete"></a>
# **productsIdDelete**
> productsIdDelete(xDb, xSaldiuser, xApikey, id)

Delete a product

### Example
```javascript
import {PblmRestApi} from 'pblm_rest_api';
let defaultClient = PblmRestApi.ApiClient.instance;

// Configure API key authorization: ApiKeyAuth
let ApiKeyAuth = defaultClient.authentications['ApiKeyAuth'];
ApiKeyAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//ApiKeyAuth.apiKeyPrefix = 'Token';

// Configure API key authorization: DatabaseAuth
let DatabaseAuth = defaultClient.authentications['DatabaseAuth'];
DatabaseAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//DatabaseAuth.apiKeyPrefix = 'Token';

// Configure API key authorization: UserAuth
let UserAuth = defaultClient.authentications['UserAuth'];
UserAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//UserAuth.apiKeyPrefix = 'Token';

let apiInstance = new PblmRestApi.ProductsApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.productsIdDelete(xDb, xSaldiuser, xApikey, id, (error, data, response) => {
  if (error) {
    console.error(error);
  } else {
    console.log('API called successfully.');
  }
});
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **xDb** | **String**| Database identifier | 
 **xSaldiuser** | **String**| User identifier | 
 **xApikey** | **String**| API key for authentication | 
 **id** | **Number**|  | 

### Return type

null (empty response body)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

<a name="productsIdGet"></a>
# **productsIdGet**
> ProductResponse productsIdGet(xDb, xSaldiuser, xApikey, id)

Get a specific product

### Example
```javascript
import {PblmRestApi} from 'pblm_rest_api';
let defaultClient = PblmRestApi.ApiClient.instance;

// Configure API key authorization: ApiKeyAuth
let ApiKeyAuth = defaultClient.authentications['ApiKeyAuth'];
ApiKeyAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//ApiKeyAuth.apiKeyPrefix = 'Token';

// Configure API key authorization: DatabaseAuth
let DatabaseAuth = defaultClient.authentications['DatabaseAuth'];
DatabaseAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//DatabaseAuth.apiKeyPrefix = 'Token';

// Configure API key authorization: UserAuth
let UserAuth = defaultClient.authentications['UserAuth'];
UserAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//UserAuth.apiKeyPrefix = 'Token';

let apiInstance = new PblmRestApi.ProductsApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.productsIdGet(xDb, xSaldiuser, xApikey, id, (error, data, response) => {
  if (error) {
    console.error(error);
  } else {
    console.log('API called successfully. Returned data: ' + data);
  }
});
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **xDb** | **String**| Database identifier | 
 **xSaldiuser** | **String**| User identifier | 
 **xApikey** | **String**| API key for authentication | 
 **id** | **Number**|  | 

### Return type

[**ProductResponse**](ProductResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

<a name="productsIdPut"></a>
# **productsIdPut**
> productsIdPut(body, xDb, xSaldiuser, xApikey, id)

Update a product

### Example
```javascript
import {PblmRestApi} from 'pblm_rest_api';
let defaultClient = PblmRestApi.ApiClient.instance;

// Configure API key authorization: ApiKeyAuth
let ApiKeyAuth = defaultClient.authentications['ApiKeyAuth'];
ApiKeyAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//ApiKeyAuth.apiKeyPrefix = 'Token';

// Configure API key authorization: DatabaseAuth
let DatabaseAuth = defaultClient.authentications['DatabaseAuth'];
DatabaseAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//DatabaseAuth.apiKeyPrefix = 'Token';

// Configure API key authorization: UserAuth
let UserAuth = defaultClient.authentications['UserAuth'];
UserAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//UserAuth.apiKeyPrefix = 'Token';

let apiInstance = new PblmRestApi.ProductsApi();
let body = new PblmRestApi.UpdateProductRequest(); // UpdateProductRequest | 
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.productsIdPut(body, xDb, xSaldiuser, xApikey, id, (error, data, response) => {
  if (error) {
    console.error(error);
  } else {
    console.log('API called successfully.');
  }
});
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **body** | [**UpdateProductRequest**](UpdateProductRequest.md)|  | 
 **xDb** | **String**| Database identifier | 
 **xSaldiuser** | **String**| User identifier | 
 **xApikey** | **String**| API key for authentication | 
 **id** | **Number**|  | 

### Return type

null (empty response body)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

<a name="productsPost"></a>
# **productsPost**
> ProductResponse productsPost(body, xDb, xSaldiuser, xApikey)

Create a new product

### Example
```javascript
import {PblmRestApi} from 'pblm_rest_api';
let defaultClient = PblmRestApi.ApiClient.instance;

// Configure API key authorization: ApiKeyAuth
let ApiKeyAuth = defaultClient.authentications['ApiKeyAuth'];
ApiKeyAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//ApiKeyAuth.apiKeyPrefix = 'Token';

// Configure API key authorization: DatabaseAuth
let DatabaseAuth = defaultClient.authentications['DatabaseAuth'];
DatabaseAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//DatabaseAuth.apiKeyPrefix = 'Token';

// Configure API key authorization: UserAuth
let UserAuth = defaultClient.authentications['UserAuth'];
UserAuth.apiKey = 'YOUR API KEY';
// Uncomment the following line to set a prefix for the API key, e.g. "Token" (defaults to null)
//UserAuth.apiKeyPrefix = 'Token';

let apiInstance = new PblmRestApi.ProductsApi();
let body = new PblmRestApi.CreateProductRequest(); // CreateProductRequest | 
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication

apiInstance.productsPost(body, xDb, xSaldiuser, xApikey, (error, data, response) => {
  if (error) {
    console.error(error);
  } else {
    console.log('API called successfully. Returned data: ' + data);
  }
});
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **body** | [**CreateProductRequest**](CreateProductRequest.md)|  | 
 **xDb** | **String**| Database identifier | 
 **xSaldiuser** | **String**| User identifier | 
 **xApikey** | **String**| API key for authentication | 

### Return type

[**ProductResponse**](ProductResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

