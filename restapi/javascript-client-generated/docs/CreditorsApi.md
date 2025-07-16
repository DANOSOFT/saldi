# PblmRestApi.CreditorsApi

All URIs are relative to *https://virtserver.swaggerhub.com/saldi-dc1/swagger/1.0.0*

Method | HTTP request | Description
------------- | ------------- | -------------
[**creditorCreditorsGet**](CreditorsApi.md#creditorCreditorsGet) | **GET** /creditor/creditors | Get all creditors
[**creditorCreditorsIdDelete**](CreditorsApi.md#creditorCreditorsIdDelete) | **DELETE** /creditor/creditors/{id} | Delete a creditor
[**creditorCreditorsIdGet**](CreditorsApi.md#creditorCreditorsIdGet) | **GET** /creditor/creditors/{id} | Get a specific creditor
[**creditorCreditorsIdPut**](CreditorsApi.md#creditorCreditorsIdPut) | **PUT** /creditor/creditors/{id} | Update a creditor
[**creditorCreditorsPost**](CreditorsApi.md#creditorCreditorsPost) | **POST** /creditor/creditors | Create a new creditor

<a name="creditorCreditorsGet"></a>
# **creditorCreditorsGet**
> CustomerListResponse creditorCreditorsGet(xDb, xSaldiuser, xApikey)

Get all creditors

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

let apiInstance = new PblmRestApi.CreditorsApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication

apiInstance.creditorCreditorsGet(xDb, xSaldiuser, xApikey, (error, data, response) => {
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

<a name="creditorCreditorsIdDelete"></a>
# **creditorCreditorsIdDelete**
> creditorCreditorsIdDelete(xDb, xSaldiuser, xApikey, id)

Delete a creditor

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

let apiInstance = new PblmRestApi.CreditorsApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.creditorCreditorsIdDelete(xDb, xSaldiuser, xApikey, id, (error, data, response) => {
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

<a name="creditorCreditorsIdGet"></a>
# **creditorCreditorsIdGet**
> CustomerResponse creditorCreditorsIdGet(xDb, xSaldiuser, xApikey, id)

Get a specific creditor

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

let apiInstance = new PblmRestApi.CreditorsApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.creditorCreditorsIdGet(xDb, xSaldiuser, xApikey, id, (error, data, response) => {
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

<a name="creditorCreditorsIdPut"></a>
# **creditorCreditorsIdPut**
> creditorCreditorsIdPut(body, xDb, xSaldiuser, xApikey, id)

Update a creditor

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

let apiInstance = new PblmRestApi.CreditorsApi();
let body = new PblmRestApi.UpdateCustomerRequest(); // UpdateCustomerRequest | 
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.creditorCreditorsIdPut(body, xDb, xSaldiuser, xApikey, id, (error, data, response) => {
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

<a name="creditorCreditorsPost"></a>
# **creditorCreditorsPost**
> creditorCreditorsPost(body, xDb, xSaldiuser, xApikey)

Create a new creditor

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

let apiInstance = new PblmRestApi.CreditorsApi();
let body = new PblmRestApi.CreateCustomerRequest(); // CreateCustomerRequest | 
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication

apiInstance.creditorCreditorsPost(body, xDb, xSaldiuser, xApikey, (error, data, response) => {
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
 **body** | [**CreateCustomerRequest**](CreateCustomerRequest.md)|  | 
 **xDb** | **String**| Database identifier | 
 **xSaldiuser** | **String**| User identifier | 
 **xApikey** | **String**| API key for authentication | 

### Return type

null (empty response body)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

