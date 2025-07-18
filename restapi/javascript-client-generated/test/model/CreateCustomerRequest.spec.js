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
    describe('CreateCustomerRequest', function() {
      beforeEach(function() {
        instance = new PblmRestApi.CreateCustomerRequest();
      });

      it('should create an instance of CreateCustomerRequest', function() {
        // TODO: update the code to test CreateCustomerRequest
        expect(instance).to.be.a(PblmRestApi.CreateCustomerRequest);
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

      it('should have the property bankNavn (base name: "bank_navn")', function() {
        // TODO: update the code to test the property bankNavn
        expect(instance).to.have.property('bankNavn');
        // expect(instance.bankNavn).to.be(expectedValueLiteral);
      });

      it('should have the property bankReg (base name: "bank_reg")', function() {
        // TODO: update the code to test the property bankReg
        expect(instance).to.have.property('bankReg');
        // expect(instance.bankReg).to.be(expectedValueLiteral);
      });

      it('should have the property bankKonto (base name: "bank_konto")', function() {
        // TODO: update the code to test the property bankKonto
        expect(instance).to.have.property('bankKonto');
        // expect(instance.bankKonto).to.be(expectedValueLiteral);
      });

      it('should have the property notes (base name: "notes")', function() {
        // TODO: update the code to test the property notes
        expect(instance).to.have.property('notes');
        // expect(instance.notes).to.be(expectedValueLiteral);
      });

      it('should have the property betalingsbet (base name: "betalingsbet")', function() {
        // TODO: update the code to test the property betalingsbet
        expect(instance).to.have.property('betalingsbet');
        // expect(instance.betalingsbet).to.be(expectedValueLiteral);
      });

      it('should have the property betalingsdage (base name: "betalingsdage")', function() {
        // TODO: update the code to test the property betalingsdage
        expect(instance).to.have.property('betalingsdage');
        // expect(instance.betalingsdage).to.be(expectedValueLiteral);
      });

      it('should have the property ean (base name: "ean")', function() {
        // TODO: update the code to test the property ean
        expect(instance).to.have.property('ean');
        // expect(instance.ean).to.be(expectedValueLiteral);
      });

      it('should have the property fornavn (base name: "fornavn")', function() {
        // TODO: update the code to test the property fornavn
        expect(instance).to.have.property('fornavn');
        // expect(instance.fornavn).to.be(expectedValueLiteral);
      });

      it('should have the property efternavn (base name: "efternavn")', function() {
        // TODO: update the code to test the property efternavn
        expect(instance).to.have.property('efternavn');
        // expect(instance.efternavn).to.be(expectedValueLiteral);
      });

    });
  });

}));
