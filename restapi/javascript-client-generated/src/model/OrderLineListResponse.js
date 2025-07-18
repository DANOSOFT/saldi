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
import OrderLineResponse from './OrderLineResponse';

/**
 * The OrderLineListResponse model module.
 * @module model/OrderLineListResponse
 * @version 1.0.0
 */
export default class OrderLineListResponse {
  /**
   * Constructs a new <code>OrderLineListResponse</code>.
   * @alias module:model/OrderLineListResponse
   * @class
   */
  constructor() {
  }

  /**
   * Constructs a <code>OrderLineListResponse</code> from a plain JavaScript object, optionally creating a new instance.
   * Copies all relevant properties from <code>data</code> to <code>obj</code> if supplied or a new instance if not.
   * @param {Object} data The plain JavaScript object bearing properties of interest.
   * @param {module:model/OrderLineListResponse} obj Optional instance to populate.
   * @return {module:model/OrderLineListResponse} The populated <code>OrderLineListResponse</code> instance.
   */
  static constructFromObject(data, obj) {
    if (data) {
      obj = obj || new OrderLineListResponse();
      if (data.hasOwnProperty('success'))
        obj.success = ApiClient.convertToType(data['success'], 'Boolean');
      if (data.hasOwnProperty('data'))
        obj.data = ApiClient.convertToType(data['data'], [OrderLineResponse]);
    }
    return obj;
  }
}

/**
 * @member {Boolean} success
 */
OrderLineListResponse.prototype.success = undefined;

/**
 * @member {Array.<module:model/OrderLineResponse>} data
 */
OrderLineListResponse.prototype.data = undefined;

