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
import CreateOrderRequest from '../model/CreateOrderRequest';
import ErrorResponse from '../model/ErrorResponse';
import OrderListResponse from '../model/OrderListResponse';
import OrderResponse from '../model/OrderResponse';

/**
* CreditorOrders service.
* @module api/CreditorOrdersApi
* @version 1.0.0
*/
export default class CreditorOrdersApi {

    /**
    * Constructs a new CreditorOrdersApi. 
    * @alias module:api/CreditorOrdersApi
    * @class
    * @param {module:ApiClient} [apiClient] Optional API client implementation to use,
    * default to {@link module:ApiClient#instanc
    e} if unspecified.
    */
    constructor(apiClient) {
        this.apiClient = apiClient || ApiClient.instance;
    }

    /**
     * Callback function to receive the result of the creditorOrdersGet operation.
     * @callback moduleapi/CreditorOrdersApi~creditorOrdersGetCallback
     * @param {String} error Error message, if any.
     * @param {module:model/OrderListResponse{ data The data returned by the service call.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Get all creditor orders
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {Object} opts Optional parameters
     * @param {Number} opts.limit  (default to <.>)
     * @param {String} opts.orderBy  (default to <.>)
     * @param {module:model/String} opts.orderDirection  (default to <.>)
     * @param {Date} opts.fromDate 
     * @param {Date} opts.toDate 
     * @param {module:api/CreditorOrdersApi~creditorOrdersGetCallback} callback The callback function, accepting three arguments: error, data, response
     * data is of type: {@link <&vendorExtensions.x-jsdoc-type>}
     */
    creditorOrdersGet(xDb, xSaldiuser, xApikey, opts, callback) {
      opts = opts || {};
      let postBody = null;
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling creditorOrdersGet");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling creditorOrdersGet");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling creditorOrdersGet");
      }

      let pathParams = {
        
      };
      let queryParams = {
        'limit': opts['limit'],'orderBy': opts['orderBy'],'orderDirection': opts['orderDirection'],'fromDate': opts['fromDate'],'toDate': opts['toDate']
      };
      let headerParams = {
        'x-db': xDb,'x-saldiuser': xSaldiuser,'x-apikey': xApikey
      };
      let formParams = {
        
      };

      let authNames = ['ApiKeyAuth', 'DatabaseAuth', 'UserAuth'];
      let contentTypes = [];
      let accepts = ['application/json'];
      let returnType = OrderListResponse;

      return this.apiClient.callApi(
        '/creditor/orders', 'GET',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }
    /**
     * Callback function to receive the result of the creditorOrdersIdGet operation.
     * @callback moduleapi/CreditorOrdersApi~creditorOrdersIdGetCallback
     * @param {String} error Error message, if any.
     * @param {module:model/OrderResponse{ data The data returned by the service call.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Get a specific creditor order
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {Number} id 
     * @param {module:api/CreditorOrdersApi~creditorOrdersIdGetCallback} callback The callback function, accepting three arguments: error, data, response
     * data is of type: {@link <&vendorExtensions.x-jsdoc-type>}
     */
    creditorOrdersIdGet(xDb, xSaldiuser, xApikey, id, callback) {
      
      let postBody = null;
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling creditorOrdersIdGet");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling creditorOrdersIdGet");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling creditorOrdersIdGet");
      }
      // verify the required parameter 'id' is set
      if (id === undefined || id === null) {
        throw new Error("Missing the required parameter 'id' when calling creditorOrdersIdGet");
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
      let returnType = OrderResponse;

      return this.apiClient.callApi(
        '/creditor/orders/{id}', 'GET',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }
    /**
     * Callback function to receive the result of the creditorOrdersPost operation.
     * @callback moduleapi/CreditorOrdersApi~creditorOrdersPostCallback
     * @param {String} error Error message, if any.
     * @param {module:model/OrderResponse{ data The data returned by the service call.
     * @param {String} response The complete HTTP response.
     */

    /**
     * Create a new creditor order
     * @param {module:model/CreateOrderRequest} body 
     * @param {String} xDb Database identifier
     * @param {String} xSaldiuser User identifier
     * @param {String} xApikey API key for authentication
     * @param {module:api/CreditorOrdersApi~creditorOrdersPostCallback} callback The callback function, accepting three arguments: error, data, response
     * data is of type: {@link <&vendorExtensions.x-jsdoc-type>}
     */
    creditorOrdersPost(body, xDb, xSaldiuser, xApikey, callback) {
      
      let postBody = body;
      // verify the required parameter 'body' is set
      if (body === undefined || body === null) {
        throw new Error("Missing the required parameter 'body' when calling creditorOrdersPost");
      }
      // verify the required parameter 'xDb' is set
      if (xDb === undefined || xDb === null) {
        throw new Error("Missing the required parameter 'xDb' when calling creditorOrdersPost");
      }
      // verify the required parameter 'xSaldiuser' is set
      if (xSaldiuser === undefined || xSaldiuser === null) {
        throw new Error("Missing the required parameter 'xSaldiuser' when calling creditorOrdersPost");
      }
      // verify the required parameter 'xApikey' is set
      if (xApikey === undefined || xApikey === null) {
        throw new Error("Missing the required parameter 'xApikey' when calling creditorOrdersPost");
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
      let returnType = OrderResponse;

      return this.apiClient.callApi(
        '/creditor/orders', 'POST',
        pathParams, queryParams, headerParams, formParams, postBody,
        authNames, contentTypes, accepts, returnType, callback
      );
    }

}