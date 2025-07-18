/*
 * PBLM REST API
 * A comprehensive REST API for managing orders, customers, products, inventory, VAT, groups, and currencies.  ## Authentication All endpoints require authentication via headers: - `x-db`: Database identifier - `x-saldiuser`: User identifier - `x-apikey`: API key for authentication 
 *
 * OpenAPI spec version: 1.0.0
 *
 * NOTE: This class is auto generated by the swagger code generator program.
 * https://github.com/swagger-api/swagger-codegen.git
 *
 * Swagger Codegen version: 3.0.69
 *
 * Do not edit the class manually.
 *
 */
import ApiClient from "../ApiClient";
import CreateVatRequest from '../model/CreateVatRequest';
import ErrorResponse from '../model/ErrorResponse';
import UpdateVatRequest from '../model/UpdateVatRequest';
import VatListResponse from '../model/VatListResponse';
import VatResponse from '../model/VatResponse';

/**
* VAT service.
* @module api/VATApi
* @version 1.0.0
*/
export default class VATApi {

    /**
    * Constructs a new VATApi. 
    * @alias module:api/VATApi
    * @class
    * @param {module:ApiClient} [apiClient] Optional API client implementation to use,
    * default to {@link module:ApiClient#instanc
    e} if unspecified.
    */
    constructor(apiClient) {
        this.apiClient = apiClient || ApiClient.instance;
    }

    /**
     * Callback function to receive the result of the vatGet operation.
     * @callback moduleapi/VATApi~vatGetCallback
     * @param {String} error Error message, if any.
     * @param {module:model/VatListResponse{ data The data returned by the service call.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Get all VAT items
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {Object} opts Optional parameters
     * @param {module:model/String} opts.orderBy  (default to <.>)
     * @param {module:model/String} opts.orderDirection  (default to <.>)
     * @param {module:model/String} opts.field 
     * @param {String} opts.value 
     * @param {module:api/VATApi~vatGetCallback} callback The callback function, accepting three arguments: error, data, response
     * data is of type: {@link <&vendorExtensions.x-jsdoc-type>}
     */
    vatGet(xDb, xSaldiuser, xApikey, opts, callback) {
      opts = opts || {};
      let postBody = null;
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling vatGet");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling vatGet");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling vatGet");
      }

      let pathParams = {
        
      };
      let queryParams = {
        'orderBy': opts['orderBy'],'orderDirection': opts['orderDirection'],'field': opts['field'],'value': opts['value']
      };
      let headerParams = {
        'x-db': xDb,'x-saldiuser': xSaldiuser,'x-apikey': xApikey
      };
      let formParams = {
        
      };

      let authNames = ['ApiKeyAuth', 'DatabaseAuth', 'UserAuth'];
      let contentTypes = [];
      let accepts = ['application/json'];
      let returnType = VatListResponse;

      return this.apiClient.callApi(
        '/vat', 'GET',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }
    /**
     * Callback function to receive the result of the vatIdDelete operation.
     * @callback moduleapi/VATApi~vatIdDeleteCallback
     * @param {String} error Error message, if any.
     * @param data This operation does not return a value.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Delete a VAT item
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {Number} id 
     * @param {module:api/VATApi~vatIdDeleteCallback} callback The callback function, accepting three arguments: error, data, response
     */
    vatIdDelete(xDb, xSaldiuser, xApikey, id, callback) {
      
      let postBody = null;
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling vatIdDelete");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling vatIdDelete");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling vatIdDelete");
      }
      // verify the required parameter 'id' is set
      if (id === undefined || id === null) {
        throw new Error("Missing the required parameter 'id' when calling vatIdDelete");
      }

      let pathParams = {
        'id': id
      };
      let queryParams = {
        
      };
      let headerParams = {
        'x-db': xDb,'x-saldiuser': xSaldiuser,'x-apikey': xApikey
      };
      let formParams = {
        
      };

      let authNames = ['ApiKeyAuth', 'DatabaseAuth', 'UserAuth'];
      let contentTypes = [];
      let accepts = ['application/json'];
      let returnType = null;

      return this.apiClient.callApi(
        '/vat/{id}', 'DELETE',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }
    /**
     * Callback function to receive the result of the vatIdGet operation.
     * @callback moduleapi/VATApi~vatIdGetCallback
     * @param {String} error Error message, if any.
     * @param {module:model/VatResponse{ data The data returned by the service call.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Get a specific VAT item
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {Number} id 
     * @param {module:api/VATApi~vatIdGetCallback} callback The callback function, accepting three arguments: error, data, response
     * data is of type: {@link <&vendorExtensions.x-jsdoc-type>}
     */
    vatIdGet(xDb, xSaldiuser, xApikey, id, callback) {
      
      let postBody = null;
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling vatIdGet");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling vatIdGet");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling vatIdGet");
      }
      // verify the required parameter 'id' is set
      if (id === undefined || id === null) {
        throw new Error("Missing the required parameter 'id' when calling vatIdGet");
      }

      let pathParams = {
        'id': id
      };
      let queryParams = {
        
      };
      let headerParams = {
        'x-db': xDb,'x-saldiuser': xSaldiuser,'x-apikey': xApikey
      };
      let formParams = {
        
      };

      let authNames = ['ApiKeyAuth', 'DatabaseAuth', 'UserAuth'];
      let contentTypes = [];
      let accepts = ['application/json'];
      let returnType = VatResponse;

      return this.apiClient.callApi(
        '/vat/{id}', 'GET',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }
    /**
     * Callback function to receive the result of the vatIdPut operation.
     * @callback moduleapi/VATApi~vatIdPutCallback
     * @param {String} error Error message, if any.
     * @param {module:model/VatResponse{ data The data returned by the service call.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Update a VAT item
     * @param {module:model/UpdateVatRequest} body 
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {Number} id 
     * @param {module:api/VATApi~vatIdPutCallback} callback The callback function, accepting three arguments: error, data, response
     * data is of type: {@link <&vendorExtensions.x-jsdoc-type>}
     */
    vatIdPut(body, xDb, xSaldiuser, xApikey, id, callback) {
      
      let postBody = body;
      // verify the required parameter 'body' is set
      if (body === undefined || body === null) {
        throw new Error("Missing the required parameter 'body' when calling vatIdPut");
      }
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling vatIdPut");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling vatIdPut");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling vatIdPut");
      }
      // verify the required parameter 'id' is set
      if (id === undefined || id === null) {
        throw new Error("Missing the required parameter 'id' when calling vatIdPut");
      }

      let pathParams = {
        'id': id
      };
      let queryParams = {
        
      };
      let headerParams = {
        'x-db': xDb,'x-saldiuser': xSaldiuser,'x-apikey': xApikey
      };
      let formParams = {
        
      };

      let authNames = ['ApiKeyAuth', 'DatabaseAuth', 'UserAuth'];
      let contentTypes = ['application/json'];
      let accepts = ['application/json'];
      let returnType = VatResponse;

      return this.apiClient.callApi(
        '/vat/{id}', 'PUT',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }
    /**
     * Callback function to receive the result of the vatPost operation.
     * @callback moduleapi/VATApi~vatPostCallback
     * @param {String} error Error message, if any.
     * @param {module:model/VatResponse{ data The data returned by the service call.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Create a new VAT item
     * @param {module:model/CreateVatRequest} body 
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {module:api/VATApi~vatPostCallback} callback The callback function, accepting three arguments: error, data, response
     * data is of type: {@link <&vendorExtensions.x-jsdoc-type>}
     */
    vatPost(body, xDb, xSaldiuser, xApikey, callback) {
      
      let postBody = body;
      // verify the required parameter 'body' is set
      if (body === undefined || body === null) {
        throw new Error("Missing the required parameter 'body' when calling vatPost");
      }
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling vatPost");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling vatPost");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling vatPost");
      }

      let pathParams = {
        
      };
      let queryParams = {
        
      };
      let headerParams = {
        'x-db': xDb,'x-saldiuser': xSaldiuser,'x-apikey': xApikey
      };
      let formParams = {
        
      };

      let authNames = ['ApiKeyAuth', 'DatabaseAuth', 'UserAuth'];
      let contentTypes = ['application/json'];
      let accepts = ['application/json'];
      let returnType = VatResponse;

      return this.apiClient.callApi(
        '/vat', 'POST',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }

}