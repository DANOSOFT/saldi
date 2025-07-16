# PblmRestApi.CreditorGroupsApi

All URIs are relative to *https://virtserver.swaggerhub.com/saldi-dc1/swagger/1.0.0*

Method | HTTP request | Description
------------- | ------------- | -------------
[**creditorGroupsGet**](CreditorGroupsApi.md#creditorGroupsGet) | **GET** /creditor/groups | Get all creditor groups
[**creditorGroupsIdDelete**](CreditorGroupsApi.md#creditorGroupsIdDelete) | **DELETE** /creditor/groups/{id} | Delete a creditor group
[**creditorGroupsIdGet**](CreditorGroupsApi.md#creditorGroupsIdGet) | **GET** /creditor/groups/{id} | Get a specific creditor group
[**creditorGroupsIdPut**](CreditorGroupsApi.md#creditorGroupsIdPut) | **PUT** /creditor/groups/{id} | Update a creditor group
[**creditorGroupsPost**](CreditorGroupsApi.md#creditorGroupsPost) | **POST** /creditor/groups | Create a new creditor group

<a name="creditorGroupsGet"></a>
# **creditorGroupsGet**
> CreditorGroupListResponse creditorGroupsGet(xDb, xSaldiuser, xApikey, opts)

Get all creditor groups

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

let apiInstance = new PblmRestApi.CreditorGroupsApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let opts = { 
  'orderBy': "kodenr", // String | 
  'orderDirection': "ASC", // String | 
  'field': "field_example", // String | 
  'value': "value_example" // String | 
};
apiInstance.creditorGroupsGet(xDb, xSaldiuser, xApikey, opts, (error, data, response) => {
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
 **orderBy** | **String**|  | [optional] [default to kodenr]
 **orderDirection** | **String**|  | [optional] [default to ASC]
 **field** | **String**|  | [optional] 
 **value** | **String**|  | [optional] 

### Return type

[**CreditorGroupListResponse**](CreditorGroupListResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

<a name="creditorGroupsIdDelete"></a>
# **creditorGroupsIdDelete**
> creditorGroupsIdDelete(xDb, xSaldiuser, xApikey, id)

Delete a creditor group

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

let apiInstance = new PblmRestApi.CreditorGroupsApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.creditorGroupsIdDelete(xDb, xSaldiuser, xApikey, id, (error, data, response) => {
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

<a name="creditorGroupsIdGet"></a>
# **creditorGroupsIdGet**
> CreditorGroupResponse creditorGroupsIdGet(xDb, xSaldiuser, xApikey, id)

Get a specific creditor group

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

let apiInstance = new PblmRestApi.CreditorGroupsApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.creditorGroupsIdGet(xDb, xSaldiuser, xApikey, id, (error, data, response) => {
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

[**CreditorGroupResponse**](CreditorGroupResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

<a name="creditorGroupsIdPut"></a>
# **creditorGroupsIdPut**
> CreditorGroupResponse creditorGroupsIdPut(body, xDb, xSaldiuser, xApikey, id)

Update a creditor group

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

let apiInstance = new PblmRestApi.CreditorGroupsApi();
let body = new PblmRestApi.UpdateCreditorGroupRequest(); // UpdateCreditorGroupRequest | 
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.creditorGroupsIdPut(body, xDb, xSaldiuser, xApikey, id, (error, data, response) => {
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
 **body** | [**UpdateCreditorGroupRequest**](UpdateCreditorGroupRequest.md)|  | 
 **xDb** | **String**| Database identifier | 
 **xSaldiuser** | **String**| User identifier | 
 **xApikey** | **String**| API key for authentication | 
 **id** | **Number**|  | 

### Return type

[**CreditorGroupResponse**](CreditorGroupResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

<a name="creditorGroupsPost"></a>
# **creditorGroupsPost**
> CreditorGroupResponse creditorGroupsPost(body, xDb, xSaldiuser, xApikey)

Create a new creditor group

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

let apiInstance = new PblmRestApi.CreditorGroupsApi();
let body = new PblmRestApi.CreateCreditorGroupRequest(); // CreateCreditorGroupRequest | 
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication

apiInstance.creditorGroupsPost(body, xDb, xSaldiuser, xApikey, (error, data, response) => {
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
 **body** | [**CreateCreditorGroupRequest**](CreateCreditorGroupRequest.md)|  | 
 **xDb** | **String**| Database identifier | 
 **xSaldiuser** | **String**| User identifier | 
 **xApikey** | **String**| API key for authentication | 

### Return type

[**CreditorGroupResponse**](CreditorGroupResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

