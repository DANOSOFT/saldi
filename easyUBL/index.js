const data = {
    "invoiceCreditnote": "string",
    "id": "string",
    "issueDate": "2023-06-08T08:21:23.972Z",
    "dueDate": "2023-06-08T08:21:23.972Z",
    "deliveryDate": "2023-06-08T08:21:23.972Z",
    "salesOrderID": "1000",
    "note": "Fra saldi til Winfinans test",
    "buyerReference": "jl",
    "accountingCost": "1010",
    "accountingCustomerParty": {
      "endpointId": "DK32879381",
      "endpointIdType": "DK:CVR",
      "name": "Winfinans test",
      "companyId": "DK32879381",
      "postalAddress": {
        "streetName": "Betonvej",
        "buildingNumber": "10",
        "inhouseMail": "",
        "additionalStreetName": "",
        "attentionName": "Jørgen Lavesen",
        "cityName": "Roskilde",
        "postalCode": "4000",
        "countrySubentity": "",
        "addressLine": "",
        "countryCode": "DK"
      },
      "contact": {
        "initials": "JL",
        "name": "Jørgen Lavesen",
        "telephone": "29336804",
        "electronicMail": "jl@Winfinans.dk"
      }
    },
    "documentCurrencyCode": "DKK",
    "totalAmount": 1000,
    "deliverAddress": {
      "streetName": "string",
      "buildingNumber": "string",
      "inhouseMail": "string",
      "additionalStreetName": "string",
      "attentionName": "string",
      "cityName": "string",
      "postalCode": "string",
      "countrySubentity": "string",
      "addressLine": "string",
      "countryCode": "string"
    },
    "invoiceLines": [
      {
        "id": "string",
        "quantity": 1,
        "quantityUnitCode": "stk",
        "price": 1000,
        "discountPercent": 0,
        "discountAmount": 0,
        "vatPercent": 25,
        "lineAmount": 1000,
        "priceInclTax": true,
        "taxOnProfit": true,
        "name": "101020",
        "description": "En vare vi ikke har",
        "accountingCost": "",
        "commodityCode": ""
      }
    ],
    "paymentMeansCode": "bb",
    "paymentID": "34343434"
}

const getCompanys = async () => {
    const res = await fetch("api.php?msg", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data)
    }).then(res => console.log(res))
}
const printValue = async () => {
    await getCompanys()
}

printValue()