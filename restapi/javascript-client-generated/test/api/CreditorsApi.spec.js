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
(function(root, factory) {
  if (typeof define === 'function' && define.amd) {
    // AMD.
    define(['expect.js', '../../src/index'], factory);
  } else if (typeof module === 'object' && module.exports) {
    // CommonJS-like environments that support module.exports, like Node.
    factory(require('expect.js'), require('../../src/index'));
  } else {
    // Browser globals (root is window)
    factory(root.expect, root.PblmRestApi);
  }
}(this, function(expect, PblmRestApi) {
  'use strict';

  var instance;

  beforeEach(function() {
    instance = new PblmRestApi.CreditorsApi();
  });

  describe('(package)', function() {
    describe('CreditorsApi', function() {
      describe('creditorCreditorsGet', function() {
        it('should call creditorCreditorsGet successfully', function(done) {
          // TODO: uncomment, update parameter values for creditorCreditorsGet call and complete the assertions
          /*

          instance.creditorCreditorsGet(xDb, xSaldiuser, xApikey, function(error, data, response) {
            if (error) {
              done(error);
              return;
            }
            // TODO: update response assertions
            expect(data).to.be.a(PblmRestApi.CustomerListResponse);

            done();
          });
          */
          // TODO: uncomment and complete method invocation above, then delete this line and the next:
          done();
        });
      });
      describe('creditorCreditorsIdDelete', function() {
        it('should call creditorCreditorsIdDelete successfully', function(done) {
          // TODO: uncomment, update parameter values for creditorCreditorsIdDelete call
          /*

          instance.creditorCreditorsIdDelete(xDb, xSaldiuser, xApikey, id, function(error, data, response) {
            if (error) {
              done(error);
              return;
            }

            done();
          });
          */
          // TODO: uncomment and complete method invocation above, then delete this line and the next:
          done();
        });
      });
      describe('creditorCreditorsIdGet', function() {
        it('should call creditorCreditorsIdGet successfully', function(done) {
          // TODO: uncomment, update parameter values for creditorCreditorsIdGet call and complete the assertions
          /*

          instance.creditorCreditorsIdGet(xDb, xSaldiuser, xApikey, id, function(error, data, response) {
            if (error) {
              done(error);
              return;
            }
            // TODO: update response assertions
            expect(data).to.be.a(PblmRestApi.CustomerResponse);

            done();
          });
          */
          // TODO: uncomment and complete method invocation above, then delete this line and the next:
          done();
        });
      });
      describe('creditorCreditorsIdPut', function() {
        it('should call creditorCreditorsIdPut successfully', function(done) {
          // TODO: uncomment, update parameter values for creditorCreditorsIdPut call
          /*

          instance.creditorCreditorsIdPut(body, xDb, xSaldiuser, xApikey, id, function(error, data, response) {
            if (error) {
              done(error);
              return;
            }

            done();
          });
          */
          // TODO: uncomment and complete method invocation above, then delete this line and the next:
          done();
        });
      });
      describe('creditorCreditorsPost', function() {
        it('should call creditorCreditorsPost successfully', function(done) {
          // TODO: uncomment, update parameter values for creditorCreditorsPost call
          /*

          instance.creditorCreditorsPost(body, xDb, xSaldiuser, xApikey, function(error, data, response) {
            if (error) {
              done(error);
              return;
            }

            done();
          });
          */
          // TODO: uncomment and complete method invocation above, then delete this line and the next:
          done();
        });
      });
    });
  });

}));
