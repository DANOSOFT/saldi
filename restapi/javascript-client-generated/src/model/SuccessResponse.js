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
import ApiClient from '../ApiClient';

/**
 * The SuccessResponse model module.
 * @module model/SuccessResponse
 * @version 1.0.0
 */
export default class SuccessResponse {
  /**
   * Constructs a new <code>SuccessResponse</code>.
   * @alias module:model/SuccessResponse
   * @class
   */
  constructor() {
  }

  /**
   * Constructs a <code>SuccessResponse</code> from a plain JavaScript object, optionally creating a new instance.
   * Copies all relevant properties from <code>data</code> to <code>obj</code> if supplied or a new instance if not.
   * @param {Object} data The plain JavaScript object bearing properties of interest.
   * @param {module:model/SuccessResponse} obj Optional instance to populate.
   * @return {module:model/SuccessResponse} The populated <code>SuccessResponse</code> instance.
   */
  static constructFromObject(data, obj) {
    if (data) {
      obj = obj || new SuccessResponse();
      if (data.hasOwnProperty('success'))
        obj.success = ApiClient.convertToType(data['success'], 'Boolean');
      if (data.hasOwnProperty('message'))
        obj.message = ApiClient.convertToType(data['message'], 'String');
      if (data.hasOwnProperty('data'))
        obj.data = ApiClient.convertToType(data['data'], Object);
    }
    return obj;
  }
}

/**
 * @member {Boolean} success
 */
SuccessResponse.prototype.success = undefined;

/**
 * Success message
 * @member {String} message
 */
SuccessResponse.prototype.message = undefined;

/**
 * Response data
 * @member {Object} data
 */
SuccessResponse.prototype.data = undefined;

