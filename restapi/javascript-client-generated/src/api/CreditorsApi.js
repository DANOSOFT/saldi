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
import CreateCustomerRequest from '../model/CreateCustomerRequest';
import CustomerListResponse from '../model/CustomerListResponse';
import CustomerResponse from '../model/CustomerResponse';
import ErrorResponse from '../model/ErrorResponse';
import UpdateCustomerRequest from '../model/UpdateCustomerRequest';

/**
* Creditors service.
* @module api/CreditorsApi
* @version 1.0.0
*/
export default class CreditorsApi {

    /**
    * Constructs a new CreditorsApi. 
    * @alias module:api/CreditorsApi
    * @class
    * @param {module:ApiClient} [apiClient] Optional API client implementation to use,
    * default to {@link module:ApiClient#instanc
    e} if unspecified.
    */
    constructor(apiClient) {
        this.apiClient = apiClient || ApiClient.instance;
    }

    /**
     * Callback function to receive the result of the creditorCreditorsGet operation.
     * @callback moduleapi/CreditorsApi~creditorCreditorsGetCallback
     * @param {String} error Error message, if any.
     * @param {module:model/CustomerListResponse{ data The data returned by the service call.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Get all creditors
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {module:api/CreditorsApi~creditorCreditorsGetCallback} callback The callback function, accepting three arguments: error, data, response
     * data is of type: {@link <&vendorExtensions.x-jsdoc-type>}
     */
    creditorCreditorsGet(xDb, xSaldiuser, xApikey, callback) {
      
      let postBody = null;
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling creditorCreditorsGet");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling creditorCreditorsGet");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling creditorCreditorsGet");
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
      let contentTypes = [];
      let accepts = ['application/json'];
      let returnType = CustomerListResponse;

      return this.apiClient.callApi(
        '/creditor/creditors', 'GET',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }
    /**
     * Callback function to receive the result of the creditorCreditorsIdDelete operation.
     * @callback moduleapi/CreditorsApi~creditorCreditorsIdDeleteCallback
     * @param {String} error Error message, if any.
     * @param data This operation does not return a value.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Delete a creditor
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {Number} id 
     * @param {module:api/CreditorsApi~creditorCreditorsIdDeleteCallback} callback The callback function, accepting three arguments: error, data, response
     */
    creditorCreditorsIdDelete(xDb, xSaldiuser, xApikey, id, callback) {
      
      let postBody = null;
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling creditorCreditorsIdDelete");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling creditorCreditorsIdDelete");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling creditorCreditorsIdDelete");
      }
      // verify the required parameter 'id' is set
      if (id === undefined || id === null) {
        throw new Error("Missing the required parameter 'id' when calling creditorCreditorsIdDelete");
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
        '/creditor/creditors/{id}', 'DELETE',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }
    /**
     * Callback function to receive the result of the creditorCreditorsIdGet operation.
     * @callback moduleapi/CreditorsApi~creditorCreditorsIdGetCallback
     * @param {String} error Error message, if any.
     * @param {module:model/CustomerResponse{ data The data returned by the service call.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Get a specific creditor
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {Number} id 
     * @param {module:api/CreditorsApi~creditorCreditorsIdGetCallback} callback The callback function, accepting three arguments: error, data, response
     * data is of type: {@link <&vendorExtensions.x-jsdoc-type>}
     */
    creditorCreditorsIdGet(xDb, xSaldiuser, xApikey, id, callback) {
      
      let postBody = null;
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling creditorCreditorsIdGet");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling creditorCreditorsIdGet");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling creditorCreditorsIdGet");
      }
      // verify the required parameter 'id' is set
      if (id === undefined || id === null) {
        throw new Error("Missing the required parameter 'id' when calling creditorCreditorsIdGet");
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
      let returnType = CustomerResponse;

      return this.apiClient.callApi(
        '/creditor/creditors/{id}', 'GET',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }
    /**
     * Callback function to receive the result of the creditorCreditorsIdPut operation.
     * @callback moduleapi/CreditorsApi~creditorCreditorsIdPutCallback
     * @param {String} error Error message, if any.
     * @param data This operation does not return a value.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Update a creditor
     * @param {module:model/UpdateCustomerRequest} body 
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {Number} id 
     * @param {module:api/CreditorsApi~creditorCreditorsIdPutCallback} callback The callback function, accepting three arguments: error, data, response
     */
    creditorCreditorsIdPut(body, xDb, xSaldiuser, xApikey, id, callback) {
      
      let postBody = body;
      // verify the required parameter 'body' is set
      if (body === undefined || body === null) {
        throw new Error("Missing the required parameter 'body' when calling creditorCreditorsIdPut");
      }
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling creditorCreditorsIdPut");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling creditorCreditorsIdPut");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling creditorCreditorsIdPut");
      }
      // verify the required parameter 'id' is set
      if (id === undefined || id === null) {
        throw new Error("Missing the required parameter 'id' when calling creditorCreditorsIdPut");
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
      let returnType = null;

      return this.apiClient.callApi(
        '/creditor/creditors/{id}', 'PUT',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }
    /**
     * Callback function to receive the result of the creditorCreditorsPost operation.
     * @callback moduleapi/CreditorsApi~creditorCreditorsPostCallback
     * @param {String} error Error message, if any.
     * @param data This operation does not return a value.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Create a new creditor
     * @param {module:model/CreateCustomerRequest} body 
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {module:api/CreditorsApi~creditorCreditorsPostCallback} callback The callback function, accepting three arguments: error, data, response
     */
    creditorCreditorsPost(body, xDb, xSaldiuser, xApikey, callback) {
      
      let postBody = body;
      // verify the required parameter 'body' is set
      if (body === undefined || body === null) {
        throw new Error("Missing the required parameter 'body' when calling creditorCreditorsPost");
      }
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling creditorCreditorsPost");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling creditorCreditorsPost");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling creditorCreditorsPost");
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
      let returnType = null;

      return this.apiClient.callApi(
        '/creditor/creditors', 'POST',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }

}