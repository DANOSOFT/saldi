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
    describe('VatResponse', function() {
      beforeEach(function() {
        instance = new PblmRestApi.VatResponse();
      });

      it('should create an instance of VatResponse', function() {
        // TODO: update the code to test VatResponse
        expect(instance).to.be.a(PblmRestApi.VatResponse);
      });

      it('should have the property id (base name: "id")', function() {
        // TODO: update the code to test the property id
        expect(instance).to.have.property('id');
        // expect(instance.id).to.be(expectedValueLiteral);
      });

      it('should have the property momskode (base name: "momskode")', function() {
        // TODO: update the code to test the property momskode
        expect(instance).to.have.property('momskode');
        // expect(instance.momskode).to.be(expectedValueLiteral);
      });

      it('should have the property nr (base name: "nr")', function() {
        // TODO: update the code to test the property nr
        expect(instance).to.have.property('nr');
        // expect(instance.nr).to.be(expectedValueLiteral);
      });

      it('should have the property beskrivelse (base name: "beskrivelse")', function() {
        // TODO: update the code to test the property beskrivelse
        expect(instance).to.have.property('beskrivelse');
        // expect(instance.beskrivelse).to.be(expectedValueLiteral);
      });

      it('should have the property fiscalYear (base name: "fiscal_year")', function() {
        // TODO: update the code to test the property fiscalYear
        expect(instance).to.have.property('fiscalYear');
        // expect(instance.fiscalYear).to.be(expectedValueLiteral);
      });

      it('should have the property account (base name: "account")', function() {
        // TODO: update the code to test the property account
        expect(instance).to.have.property('account');
        // expect(instance.account).to.be(expectedValueLiteral);
      });

      it('should have the property sats (base name: "sats")', function() {
        // TODO: update the code to test the property sats
        expect(instance).to.have.property('sats');
        // expect(instance.sats).to.be(expectedValueLiteral);
      });

      it('should have the property modkonto (base name: "modkonto")', function() {
        // TODO: update the code to test the property modkonto
        expect(instance).to.have.property('modkonto');
        // expect(instance.modkonto).to.be(expectedValueLiteral);
      });

      it('should have the property map (base name: "map")', function() {
        // TODO: update the code to test the property map
        expect(instance).to.have.property('map');
        // expect(instance.map).to.be(expectedValueLiteral);
      });

    });
  });

}));
