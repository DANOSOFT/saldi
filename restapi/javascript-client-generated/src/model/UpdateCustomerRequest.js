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
import CreateCustomerRequest from './CreateCustomerRequest';

/**
 * The UpdateCustomerRequest model module.
 * @module model/UpdateCustomerRequest
 * @version 1.0.0
 */
export default class UpdateCustomerRequest extends CreateCustomerRequest {
  /**
   * Constructs a new <code>UpdateCustomerRequest</code>.
   * @alias module:model/UpdateCustomerRequest
   * @class
   * @extends module:model/CreateCustomerRequest
   * @param id {} Customer ID
   * @param firmanavn {} Company name
   * @param tlf {} Phone number
   * @param email {} Email address
   */
  constructor(id, firmanavn, tlf, email) {
    super(firmanavn, tlf, email);
    this.id = id;
  }

  /**
   * Constructs a <code>UpdateCustomerRequest</code> from a plain JavaScript object, optionally creating a new instance.
   * Copies all relevant properties from <code>data</code> to <code>obj</code> if supplied or a new instance if not.
   * @param {Object} data The plain JavaScript object bearing properties of interest.
   * @param {module:model/UpdateCustomerRequest} obj Optional instance to populate.
   * @return {module:model/UpdateCustomerRequest} The populated <code>UpdateCustomerRequest</code> instance.
   */
  static constructFromObject(data, obj) {
    if (data) {
      obj = obj || new UpdateCustomerRequest();
      CreateCustomerRequest.constructFromObject(data, obj);
      if (data.hasOwnProperty('id'))
        obj.id = ApiClient.convertToType(data['id'], 'Number');
    }
    return obj;
  }
}

/**
 * Customer ID
 * @member {Number} id
 */
UpdateCustomerRequest.prototype.id = undefined;

