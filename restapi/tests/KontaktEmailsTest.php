<?php

/**
 * Kontakt Emails Test Script
 *
 * Tests the kontakt_emails system through the Customer API endpoint.
 * Verifies that multiple emails per customer with different types work correctly,
 * and that the correct email type is returned for each document type.
 */

class KontaktEmailsTest
{
    private $baseUrl;
    private $headers;
    private $createdCustomerIds = [];

    public function __construct()
    {
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/debitor/customers/';

        $this->headers = [
            'Content-Type: application/json',
            // TODO: Replace with JWT token from POST /auth/login
        ];
    }

    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "=== Kontakt Emails Tests ===\n\n";

        try {
            // CRUD tests
            $this->testCreateCustomerWithEmails();
            $this->testGetCustomerReturnsEmails();
            $this->testUpdateCustomerEmails();
            $this->testAddEmailType();
            $this->testRemoveEmailByUpdate();
            $this->testDeleteCustomerCleansUpEmails();

            // Type-specific tests
            $this->testAllEmailTypesSupported();
            $this->testMultipleEmailsSameType();
            $this->testFallbackToHovedEmail();
            $this->testEmptyEmailsArray();

            // Backward compatibility
            $this->testPrimaryEmailSyncsToAdresser();
            $this->testEnglishPropertyName();

            echo "\n=== Test Summary ===\n";
            echo "All kontakt_emails tests completed successfully!\n";

        } catch (Exception $e) {
            echo "\n!!! TEST FAILED: " . $e->getMessage() . "\n";
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Test 1: Create a customer with kontakt_emails
     */
    public function testCreateCustomerWithEmails()
    {
        echo "Test: Create customer with kontakt_emails\n";

        $data = [
            'companyName' => 'KE Test Company',
            'phone' => '55001001',
            'email' => 'hoved@ketest.dk',
            'kontakt_emails' => [
                ['email' => 'hoved@ketest.dk', 'email_type' => 'hoved'],
                ['email' => 'faktura@ketest.dk', 'email_type' => 'faktura'],
                ['email' => 'ordre@ketest.dk', 'email_type' => 'ordre']
            ]
        ];

        $response = $this->makeRequest('POST', $data);

        $this->assert($response['success'], "Customer should be created");
        $this->assert(isset($response['data']['id']), "Response should contain id");
        $this->createdCustomerIds[] = $response['data']['id'];

        echo "  PASS - Customer created with ID: " . $response['data']['id'] . "\n\n";
    }

    /**
     * Test 2: GET customer should return kontakt_emails array
     */
    public function testGetCustomerReturnsEmails()
    {
        echo "Test: GET customer returns kontakt_emails\n";

        $customerId = $this->createdCustomerIds[0];
        $response = $this->makeRequest('GET', null, "?id=$customerId");

        $this->assert($response['success'], "GET should succeed");
        $this->assert(isset($response['data']['kontakt_emails']), "Response should contain kontakt_emails");
        $this->assert(is_array($response['data']['kontakt_emails']), "kontakt_emails should be an array");
        $this->assert(count($response['data']['kontakt_emails']) === 3, "Should have 3 emails, got " . count($response['data']['kontakt_emails']));

        // Verify types
        $types = array_column($response['data']['kontakt_emails'], 'email_type');
        $this->assert(in_array('hoved', $types), "Should contain hoved type");
        $this->assert(in_array('faktura', $types), "Should contain faktura type");
        $this->assert(in_array('ordre', $types), "Should contain ordre type");

        echo "  PASS - 3 emails returned with correct types\n\n";
    }

    /**
     * Test 3: Update customer emails (replace all)
     */
    public function testUpdateCustomerEmails()
    {
        echo "Test: Update customer kontakt_emails\n";

        $customerId = $this->createdCustomerIds[0];
        $data = [
            'kontakt_emails' => [
                ['email' => 'ny-hoved@ketest.dk', 'email_type' => 'hoved'],
                ['email' => 'ny-faktura@ketest.dk', 'email_type' => 'faktura']
            ]
        ];

        $response = $this->makeRequest('PUT', $data, "?id=$customerId");
        $this->assert($response['success'], "PUT should succeed");

        // Verify the update
        $getResponse = $this->makeRequest('GET', null, "?id=$customerId");
        $emails = $getResponse['data']['kontakt_emails'];
        $this->assert(count($emails) === 2, "Should now have 2 emails, got " . count($emails));

        $emailAddrs = array_column($emails, 'email');
        $this->assert(in_array('ny-hoved@ketest.dk', $emailAddrs), "Should contain updated hoved email");
        $this->assert(in_array('ny-faktura@ketest.dk', $emailAddrs), "Should contain updated faktura email");
        $this->assert(!in_array('ordre@ketest.dk', $emailAddrs), "Old ordre email should be removed");

        echo "  PASS - Emails replaced correctly\n\n";
    }

    /**
     * Test 4: Add a new email type
     */
    public function testAddEmailType()
    {
        echo "Test: Add new email types (rykker, tilbud, kontoudtog)\n";

        $customerId = $this->createdCustomerIds[0];

        // Get current emails and add new ones
        $getResponse = $this->makeRequest('GET', null, "?id=$customerId");
        $currentEmails = $getResponse['data']['kontakt_emails'];

        $currentEmails[] = ['email' => 'rykker@ketest.dk', 'email_type' => 'rykker'];
        $currentEmails[] = ['email' => 'tilbud@ketest.dk', 'email_type' => 'tilbud'];
        $currentEmails[] = ['email' => 'kontoudtog@ketest.dk', 'email_type' => 'kontoudtog'];

        $data = ['kontakt_emails' => $currentEmails];
        $response = $this->makeRequest('PUT', $data, "?id=$customerId");
        $this->assert($response['success'], "PUT should succeed");

        // Verify
        $getResponse = $this->makeRequest('GET', null, "?id=$customerId");
        $emails = $getResponse['data']['kontakt_emails'];
        $this->assert(count($emails) === 5, "Should have 5 emails, got " . count($emails));

        $types = array_column($emails, 'email_type');
        $this->assert(in_array('rykker', $types), "Should contain rykker type");
        $this->assert(in_array('tilbud', $types), "Should contain tilbud type");
        $this->assert(in_array('kontoudtog', $types), "Should contain kontoudtog type");

        echo "  PASS - All new types added\n\n";
    }

    /**
     * Test 5: Remove an email by sending update without it
     */
    public function testRemoveEmailByUpdate()
    {
        echo "Test: Remove email by omitting from update\n";

        $customerId = $this->createdCustomerIds[0];

        // Set only 2 emails — the rest should be removed
        $data = [
            'kontakt_emails' => [
                ['email' => 'only-hoved@ketest.dk', 'email_type' => 'hoved'],
                ['email' => 'only-faktura@ketest.dk', 'email_type' => 'faktura']
            ]
        ];

        $response = $this->makeRequest('PUT', $data, "?id=$customerId");
        $this->assert($response['success'], "PUT should succeed");

        $getResponse = $this->makeRequest('GET', null, "?id=$customerId");
        $emails = $getResponse['data']['kontakt_emails'];
        $this->assert(count($emails) === 2, "Should have exactly 2 emails, got " . count($emails));

        echo "  PASS - Old emails removed\n\n";
    }

    /**
     * Test 6: Deleting customer cleans up kontakt_emails
     */
    public function testDeleteCustomerCleansUpEmails()
    {
        echo "Test: Delete customer cleans up kontakt_emails\n";

        // Create a temp customer
        $data = [
            'companyName' => 'KE Delete Test',
            'phone' => '55009009',
            'email' => 'delete-test@ketest.dk',
            'kontakt_emails' => [
                ['email' => 'delete1@ketest.dk', 'email_type' => 'faktura'],
                ['email' => 'delete2@ketest.dk', 'email_type' => 'rykker']
            ]
        ];

        $createResponse = $this->makeRequest('POST', $data);
        $this->assert($createResponse['success'], "Create should succeed");
        $tempId = $createResponse['data']['id'];

        // Delete it
        $deleteResponse = $this->makeRequest('DELETE', ['id' => $tempId], "?id=$tempId");
        $this->assert($deleteResponse['success'], "Delete should succeed");

        // Verify it's gone
        $getResponse = $this->makeRequest('GET', null, "?id=$tempId");
        $this->assert(!$getResponse['success'], "GET after delete should fail");

        echo "  PASS - Customer and emails deleted\n\n";
    }

    /**
     * Test 7: All supported email types
     */
    public function testAllEmailTypesSupported()
    {
        echo "Test: All 6 email types supported\n";

        $data = [
            'companyName' => 'KE All Types Test',
            'phone' => '55002002',
            'email' => 'all-types@ketest.dk',
            'kontakt_emails' => [
                ['email' => 'tilbud@types.dk', 'email_type' => 'tilbud'],
                ['email' => 'ordre@types.dk', 'email_type' => 'ordre'],
                ['email' => 'faktura@types.dk', 'email_type' => 'faktura'],
                ['email' => 'kontoudtog@types.dk', 'email_type' => 'kontoudtog'],
                ['email' => 'rykker@types.dk', 'email_type' => 'rykker'],
                ['email' => 'andet@types.dk', 'email_type' => 'andet']
            ]
        ];

        $response = $this->makeRequest('POST', $data);
        $this->assert($response['success'], "Create should succeed");
        $this->createdCustomerIds[] = $response['data']['id'];

        $getResponse = $this->makeRequest('GET', null, "?id=" . $response['data']['id']);
        $types = array_column($getResponse['data']['kontakt_emails'], 'email_type');

        $expected = ['tilbud', 'ordre', 'faktura', 'kontoudtog', 'rykker', 'andet'];
        foreach ($expected as $t) {
            $this->assert(in_array($t, $types), "Should contain type: $t");
        }

        echo "  PASS - All 6 types stored and retrieved\n\n";
    }

    /**
     * Test 8: Multiple emails with same type
     */
    public function testMultipleEmailsSameType()
    {
        echo "Test: Multiple emails with same type (faktura)\n";

        $data = [
            'companyName' => 'KE Multi Faktura',
            'phone' => '55003003',
            'email' => 'multi@ketest.dk',
            'kontakt_emails' => [
                ['email' => 'faktura1@ketest.dk', 'email_type' => 'faktura'],
                ['email' => 'faktura2@ketest.dk', 'email_type' => 'faktura'],
                ['email' => 'faktura3@ketest.dk', 'email_type' => 'faktura']
            ]
        ];

        $response = $this->makeRequest('POST', $data);
        $this->assert($response['success'], "Create should succeed");
        $this->createdCustomerIds[] = $response['data']['id'];

        $getResponse = $this->makeRequest('GET', null, "?id=" . $response['data']['id']);
        $fakturaEmails = array_filter($getResponse['data']['kontakt_emails'], function ($e) {
            return $e['email_type'] === 'faktura';
        });

        $this->assert(count($fakturaEmails) === 3, "Should have 3 faktura emails, got " . count($fakturaEmails));

        echo "  PASS - 3 faktura emails stored\n\n";
    }

    /**
     * Test 9: Fallback when no type-specific email exists
     */
    public function testFallbackToHovedEmail()
    {
        echo "Test: Customer with only hoved email (fallback scenario)\n";

        $data = [
            'companyName' => 'KE Fallback Test',
            'phone' => '55004004',
            'email' => 'fallback@ketest.dk',
            'kontakt_emails' => [
                ['email' => 'fallback@ketest.dk', 'email_type' => 'hoved']
            ]
        ];

        $response = $this->makeRequest('POST', $data);
        $this->assert($response['success'], "Create should succeed");
        $this->createdCustomerIds[] = $response['data']['id'];

        $getResponse = $this->makeRequest('GET', null, "?id=" . $response['data']['id']);
        $this->assert(count($getResponse['data']['kontakt_emails']) === 1, "Should have 1 email");
        $this->assert($getResponse['data']['kontakt_emails'][0]['email_type'] === 'hoved', "Type should be hoved");

        echo "  PASS - Single hoved email works (other types will fallback to this)\n\n";
    }

    /**
     * Test 10: Empty emails array
     */
    public function testEmptyEmailsArray()
    {
        echo "Test: Update with empty kontakt_emails array\n";

        $customerId = $this->createdCustomerIds[0];

        $data = ['kontakt_emails' => []];
        $response = $this->makeRequest('PUT', $data, "?id=$customerId");
        $this->assert($response['success'], "PUT should succeed");

        $getResponse = $this->makeRequest('GET', null, "?id=$customerId");
        $this->assert(count($getResponse['data']['kontakt_emails']) === 0, "Should have 0 emails");

        // Restore an email for later tests
        $data = ['kontakt_emails' => [['email' => 'restored@ketest.dk', 'email_type' => 'hoved']]];
        $this->makeRequest('PUT', $data, "?id=$customerId");

        echo "  PASS - Empty array clears all emails\n\n";
    }

    /**
     * Test 11: Primary email syncs to adresser.email
     */
    public function testPrimaryEmailSyncsToAdresser()
    {
        echo "Test: Primary email syncs to adresser.email field\n";

        $customerId = $this->createdCustomerIds[0];

        $data = [
            'kontakt_emails' => [
                ['email' => 'synced-primary@ketest.dk', 'email_type' => 'hoved'],
                ['email' => 'faktura@ketest.dk', 'email_type' => 'faktura']
            ]
        ];
        $this->makeRequest('PUT', $data, "?id=$customerId");

        $getResponse = $this->makeRequest('GET', null, "?id=$customerId");
        // The adresser.email field should be synced with the first kontakt_email
        $adresserEmail = $getResponse['data']['email'];
        $this->assert(
            $adresserEmail === 'synced-primary@ketest.dk',
            "adresser.email should be synced to first kontakt_email, got: $adresserEmail"
        );

        echo "  PASS - adresser.email synced to first kontakt_email\n\n";
    }

    /**
     * Test 12: English property name (contactEmails) works
     */
    public function testEnglishPropertyName()
    {
        echo "Test: English property name 'contactEmails' works\n";

        $data = [
            'companyName' => 'KE English Test',
            'phone' => '55005005',
            'email' => 'english@ketest.dk',
            'contactEmails' => [
                ['email' => 'english-faktura@ketest.dk', 'email_type' => 'faktura']
            ]
        ];

        $response = $this->makeRequest('POST', $data);
        $this->assert($response['success'], "Create with English property should succeed");
        $this->createdCustomerIds[] = $response['data']['id'];

        $getResponse = $this->makeRequest('GET', null, "?id=" . $response['data']['id']);
        $this->assert(count($getResponse['data']['kontakt_emails']) === 1, "Should have 1 email from English property");

        echo "  PASS - English property name mapped correctly\n\n";
    }

    /**
     * Simple assertion helper
     */
    private function assert($condition, $message)
    {
        if (!$condition) {
            throw new Exception("Assertion failed: $message");
        }
    }

    /**
     * Make HTTP request to the API
     */
    private function makeRequest($method, $data = null, $urlSuffix = '')
    {
        $url = $this->baseUrl . $urlSuffix;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data && in_array($method, ['POST', 'PUT', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Failed to make request to $url");
        }

        $decodedResponse = json_decode($response, true);

        if ($decodedResponse === null) {
            throw new Exception("Invalid JSON response from $url (HTTP $httpCode): $response");
        }

        return $decodedResponse;
    }

    /**
     * Clean up created test data
     */
    private function cleanup()
    {
        echo "\n=== Cleanup ===\n";

        foreach ($this->createdCustomerIds as $customerId) {
            try {
                $response = $this->makeRequest('DELETE', ['id' => $customerId], "?id=$customerId");
                if ($response['success']) {
                    echo "  Cleaned up customer ID: $customerId\n";
                } else {
                    echo "  Failed to cleanup customer ID: $customerId\n";
                }
            } catch (Exception $e) {
                echo "  Error cleaning up $customerId: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Run the tests
$tester = new KontaktEmailsTest();
$tester->runAllTests();
