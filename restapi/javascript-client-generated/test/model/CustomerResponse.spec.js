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

  describe('(package)', function() {
    describe('CustomerResponse', function() {
      beforeEach(function() {
        instance = new PblmRestApi.CustomerResponse();
      });

      it('should create an instance of CustomerResponse', function() {
        // TODO: update the code to test CustomerResponse
        expect(instance).to.be.a(PblmRestApi.CustomerResponse);
      });

      it('should have the property id (base name: "id")', function() {
        // TODO: update the code to test the property id
        expect(instance).to.have.property('id');
        // expect(instance.id).to.be(expectedValueLiteral);
      });

      it('should have the property firmanavn (base name: "firmanavn")', function() {
        // TODO: update the code to test the property firmanavn
        expect(instance).to.have.property('firmanavn');
        // expect(instance.firmanavn).to.be(expectedValueLiteral);
      });

      it('should have the property tlf (base name: "tlf")', function() {
        // TODO: update the code to test the property tlf
        expect(instance).to.have.property('tlf');
        // expect(instance.tlf).to.be(expectedValueLiteral);
      });

      it('should have the property email (base name: "email")', function() {
        // TODO: update the code to test the property email
        expect(instance).to.have.property('email');
        // expect(instance.email).to.be(expectedValueLiteral);
      });

      it('should have the property addr1 (base name: "addr1")', function() {
        // TODO: update the code to test the property addr1
        expect(instance).to.have.property('addr1');
        // expect(instance.addr1).to.be(expectedValueLiteral);
      });

      it('should have the property addr2 (base name: "addr2")', function() {
        // TODO: update the code to test the property addr2
        expect(instance).to.have.property('addr2');
        // expect(instance.addr2).to.be(expectedValueLiteral);
      });

      it('should have the property postnr (base name: "postnr")', function() {
        // TODO: update the code to test the property postnr
        expect(instance).to.have.property('postnr');
        // expect(instance.postnr).to.be(expectedValueLiteral);
      });

      it('should have the property bynavn (base name: "bynavn")', function() {
        // TODO: update the code to test the property bynavn
        expect(instance).to.have.property('bynavn');
        // expect(instance.bynavn).to.be(expectedValueLiteral);
      });

      it('should have the property cvrnr (base name: "cvrnr")', function() {
        // TODO: update the code to test the property cvrnr
        expect(instance).to.have.property('cvrnr');
        // expect(instance.cvrnr).to.be(expectedValueLiteral);
      });

      it('should have the property land (base name: "land")', function() {
        // TODO: update the code to test the property land
        expect(instance).to.have.property('land');
        // expect(instance.land).to.be(expectedValueLiteral);
      });

      it('should have the property art (base name: "art")', function() {
        // TODO: update the code to test the property art
        expect(instance).to.have.property('art');
        // expect(instance.art).to.be(expectedValueLiteral);
      });

    });
  });

}));
