# PblmRestApi.DebitorGroupsApi

All URIs are relative to *https://virtserver.swaggerhub.com/saldi-dc1/swagger/1.0.0*

Method | HTTP request | Description
------------- | ------------- | -------------
[**debitorGroupsGet**](DebitorGroupsApi.md#debitorGroupsGet) | **GET** /debitor/groups | Get all debitor groups
[**debitorGroupsIdDelete**](DebitorGroupsApi.md#debitorGroupsIdDelete) | **DELETE** /debitor/groups/{id} | Delete a debitor group
[**debitorGroupsIdGet**](DebitorGroupsApi.md#debitorGroupsIdGet) | **GET** /debitor/groups/{id} | Get a specific debitor group
[**debitorGroupsIdPut**](DebitorGroupsApi.md#debitorGroupsIdPut) | **PUT** /debitor/groups/{id} | Update a debitor group
[**debitorGroupsPost**](DebitorGroupsApi.md#debitorGroupsPost) | **POST** /debitor/groups | Create a new debitor group

<a name="debitorGroupsGet"></a>
# **debitorGroupsGet**
> DebitorGroupListResponse debitorGroupsGet(xDb, xSaldiuser, xApikey, opts)

Get all debitor groups

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

let apiInstance = new PblmRestApi.DebitorGroupsApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let opts = { 
  'orderBy': "kodenr", // String | 
  'orderDirection': "ASC", // String | 
  'field': "field_example", // String | 
  'value': "value_example" // String | 
};
apiInstance.debitorGroupsGet(xDb, xSaldiuser, xApikey, opts, (error, data, response) => {
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

[**DebitorGroupListResponse**](DebitorGroupListResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

<a name="debitorGroupsIdDelete"></a>
# **debitorGroupsIdDelete**
> debitorGroupsIdDelete(xDb, xSaldiuser, xApikey, id)

Delete a debitor group

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

let apiInstance = new PblmRestApi.DebitorGroupsApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.debitorGroupsIdDelete(xDb, xSaldiuser, xApikey, id, (error, data, response) => {
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

<a name="debitorGroupsIdGet"></a>
# **debitorGroupsIdGet**
> DebitorGroupResponse debitorGroupsIdGet(xDb, xSaldiuser, xApikey, id)

Get a specific debitor group

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

let apiInstance = new PblmRestApi.DebitorGroupsApi();
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.debitorGroupsIdGet(xDb, xSaldiuser, xApikey, id, (error, data, response) => {
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

[**DebitorGroupResponse**](DebitorGroupResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

<a name="debitorGroupsIdPut"></a>
# **debitorGroupsIdPut**
> DebitorGroupResponse debitorGroupsIdPut(body, xDb, xSaldiuser, xApikey, id)

Update a debitor group

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

let apiInstance = new PblmRestApi.DebitorGroupsApi();
let body = new PblmRestApi.UpdateDebitorGroupRequest(); // UpdateDebitorGroupRequest | 
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication
let id = 56; // Number | 

apiInstance.debitorGroupsIdPut(body, xDb, xSaldiuser, xApikey, id, (error, data, response) => {
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
 **body** | [**UpdateDebitorGroupRequest**](UpdateDebitorGroupRequest.md)|  | 
 **xDb** | **String**| Database identifier | 
 **xSaldiuser** | **String**| User identifier | 
 **xApikey** | **String**| API key for authentication | 
 **id** | **Number**|  | 

### Return type

[**DebitorGroupResponse**](DebitorGroupResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

<a name="debitorGroupsPost"></a>
# **debitorGroupsPost**
> DebitorGroupResponse debitorGroupsPost(body, xDb, xSaldiuser, xApikey)

Create a new debitor group

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

let apiInstance = new PblmRestApi.DebitorGroupsApi();
let body = new PblmRestApi.CreateDebitorGroupRequest(); // CreateDebitorGroupRequest | 
let xDb = "xDb_example"; // String | Database identifier
let xSaldiuser = "xSaldiuser_example"; // String | User identifier
let xApikey = "xApikey_example"; // String | API key for authentication

apiInstance.debitorGroupsPost(body, xDb, xSaldiuser, xApikey, (error, data, response) => {
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
 **body** | [**CreateDebitorGroupRequest**](CreateDebitorGroupRequest.md)|  | 
 **xDb** | **String**| Database identifier | 
 **xSaldiuser** | **String**| User identifier | 
 **xApikey** | **String**| API key for authentication | 

### Return type

[**DebitorGroupResponse**](DebitorGroupResponse.md)

### Authorization

[ApiKeyAuth](../README.md#ApiKeyAuth), [DatabaseAuth](../README.md#DatabaseAuth), [UserAuth](../README.md#UserAuth)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

