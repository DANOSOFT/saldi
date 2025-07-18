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
    describe('CreateProductRequest', function() {
      beforeEach(function() {
        instance = new PblmRestApi.CreateProductRequest();
      });

      it('should create an instance of CreateProductRequest', function() {
        // TODO: update the code to test CreateProductRequest
        expect(instance).to.be.a(PblmRestApi.CreateProductRequest);
      });

      it('should have the property varenr (base name: "varenr")', function() {
        // TODO: update the code to test the property varenr
        expect(instance).to.have.property('varenr');
        // expect(instance.varenr).to.be(expectedValueLiteral);
      });

      it('should have the property stregkode (base name: "stregkode")', function() {
        // TODO: update the code to test the property stregkode
        expect(instance).to.have.property('stregkode');
        // expect(instance.stregkode).to.be(expectedValueLiteral);
      });

      it('should have the property beskrivelse (base name: "beskrivelse")', function() {
        // TODO: update the code to test the property beskrivelse
        expect(instance).to.have.property('beskrivelse');
        // expect(instance.beskrivelse).to.be(expectedValueLiteral);
      });

      it('should have the property salgspris (base name: "salgspris")', function() {
        // TODO: update the code to test the property salgspris
        expect(instance).to.have.property('salgspris');
        // expect(instance.salgspris).to.be(expectedValueLiteral);
      });

      it('should have the property kostpris (base name: "kostpris")', function() {
        // TODO: update the code to test the property kostpris
        expect(instance).to.have.property('kostpris');
        // expect(instance.kostpris).to.be(expectedValueLiteral);
      });

      it('should have the property size (base name: "size")', function() {
        // TODO: update the code to test the property size
        expect(instance).to.have.property('size');
        // expect(instance.size).to.be(expectedValueLiteral);
      });

    });
  });

}));
