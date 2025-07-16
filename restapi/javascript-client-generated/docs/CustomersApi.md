# PblmRestApi.CustomersApi

All URIs are relative to *https://virtserver.swaggerhub.com/saldi-dc1/swagger/1.0.0*

Method | HTTP request | Description
------------- | ------------- | -------------
[**debitorCustomersGet**](CustomersApi.md#debitorCustomersGet) | **GET** /debitor/customers | Get all customers
[**debitorCustomersIdDelete**](CustomersApi.md#debitorCustomersIdDelete) | **DELETE** /debitor/customers/{id} | Delete a customer
[**debitorCustomersIdGet**](CustomersApi.md#debitorCustomersIdGet) | **GET** /debitor/customers/{id} | Get a specific customer
[**debitorCustomersIdPut**](CustomersApi.md#debitorCustomersIdPut) | **PUT** /debitor/customers/{id} | Update a customer
[**debitorCustomersPost**](CustomersApi.md#debitorCustomersPost) | **POST** /debitor/customers | Create a new customer

<a name="debitorCustomersGet"></a>
# **debitorCustomersGet**
> CustomerListResponse debitorCustomersGet(xDb, xSaldiuser, xApikey)

Get all customers

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

let apiInstance = new PblmRestApi.CustomersApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication

apiInstance.debitorCustomersGet(xDb, xSaldiuser, xApikey, (error, data, response) => {
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

[**CustomerListResponse**](CustomerListResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

<a name="debitorCustomersIdDelete"></a>
# **debitorCustomersIdDelete**
> debitorCustomersIdDelete(xDb, xSaldiuser, xApikey, id)

Delete a customer

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

let apiInstance = new PblmRestApi.CustomersApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.debitorCustomersIdDelete(xDb, xSaldiuser, xApikey, id, (error, data, response) => {
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

<a name="debitorCustomersIdGet"></a>
# **debitorCustomersIdGet**
> CustomerResponse debitorCustomersIdGet(xDb, xSaldiuser, xApikey, id)

Get a specific customer

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

let apiInstance = new PblmRestApi.CustomersApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.debitorCustomersIdGet(xDb, xSaldiuser, xApikey, id, (error, data, response) => {
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

[**CustomerResponse**](CustomerResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

<a name="debitorCustomersIdPut"></a>
# **debitorCustomersIdPut**
> debitorCustomersIdPut(body, xDb, xSaldiuser, xApikey, id)

Update a customer

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

let apiInstance = new PblmRestApi.CustomersApi();
let body = new PblmRestApi.UpdateCustomerRequest(); // UpdateCustomerRequest | 
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.debitorCustomersIdPut(body, xDb, xSaldiuser, xApikey, id, (error, data, response) => {
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
 **body** | [**UpdateCustomerRequest**](UpdateCustomerRequest.md)|  | 
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

<a name="debitorCustomersPost"></a>
# **debitorCustomersPost**
> CustomerResponse debitorCustomersPost(body, xDb, xSaldiuser, xApikey)

Create a new customer

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

let apiInstance = new PblmRestApi.CustomersApi();
let body = new PblmRestApi.CreateCustomerRequest(); // CreateCustomerRequest | 
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication

apiInstance.debitorCustomersPost(body, xDb, xSaldiuser, xApikey, (error, data, response) => {
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
 **body** | [**CreateCustomerRequest**](CreateCustomerRequest.md)|  | 
 **xDb** | **String**| Database identifier | 
 **xSaldiuser** | **String**| User identifier | 
 **xApikey** | **String**| API key for authentication | 

### Return type

[**CustomerResponse**](CustomerResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

