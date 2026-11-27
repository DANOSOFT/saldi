<?php
// tests/characterization/debitor/InvoicedStripeDeliveryTest.test.php
//
// Regression test for review finding I17 (Stripe port, review round 1): on an invoiced order whose Udskriv til is
// Stripe (the customer pays automatically by card), staff switched Udskriv til to PDF to print a copy. The select
// submits the form on change, and debitor/ordre.php saved the choice, so the order was no longer 'Stripe': the charge
// job, the payment page's state and the reminder hold all key on it, and the invoice was never charged.
//
// Now the choice prints in that mode and is not saved: the order keeps Stripe, the page shows the chosen mode until it
// is opened again, and Udskriv prints as PDF. An invoiced order that is not Stripe still saves the choice.
//
// Drives debitor/ordre.php as a browser does, through support/run_debitor_order_page.php.
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261126 CL/ASR Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../support/PageForm.php';

final class InvoicedStripeDeliveryTest extends CharacterizationTestCase
{
    /** An invoiced sales order (status 4) with the given Udskriv til. */
    private function invoicedOrder(string $delivery): int
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['salgspris' => 100.00, 'kostpris' => 50.00]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 1]], ['status' => 4]);
        self::rows("UPDATE ordrer SET udskriv_til = $2, mail_fakt = 'on', fakturanr = '9' || id, fakturadate = CURRENT_DATE WHERE id = $1",
            [$order['id'], $delivery]);
        return (int)$order['id'];
    }

    /**
     * A GET of the order page, or a POST of its form.
     *
     * @param array<string, string> $post
     */
    private function page(int $id, array $post = []): string
    {
        $res = $this->runChildJson(
            'run_debitor_order_page.php',
            ["id=$id", CharacterizationEnv::sessionId(), $post === [] ? '' : http_build_query($post)]
        );
        $this->assertStringNotContainsString('Fatal error', $res['html'], "ordre.php?id=$id crashed");
        return $res['html'];
    }

    private function stored(int $id): string
    {
        return (string)self::one('SELECT udskriv_til FROM ordrer WHERE id = $1', [$id])['udskriv_til'];
    }

    public function test_another_print_mode_on_an_invoiced_stripe_order_prints_without_replacing_stripe(): void
    {
        $id = $this->invoicedOrder('Stripe');
        $form = PageForm::fields($this->page($id), 'ordre');
        $this->assertSame('Stripe', $form['udskriv_til'] ?? null, 'precondition: the page shows the stored Stripe');

        // Choosing PDF submits the form (onchange).
        $form['udskriv_til'] = 'PDF';
        $html = $this->page($id, $form);
        $this->assertSame('Stripe', $this->stored($id), 'the order keeps Stripe, so it is still charged (it became PDF)');
        $form = PageForm::fields($html, 'ordre');
        $this->assertSame('PDF', $form['udskriv_til'] ?? null, 'the page shows the chosen print mode');

        // Udskriv prints the invoice as PDF.
        $form['print'] = 'Udskriv';
        $html = $this->page($id, $form);
        $this->assertStringContainsString("formularprint.php?id=$id&formular=4&udskriv_til=PDF", $html);
        $this->assertSame('Stripe', $this->stored($id));

        // Opened again, the order shows its Stripe.
        $this->assertSame('Stripe', PageForm::fields($this->page($id), 'ordre')['udskriv_til'] ?? null);
    }

    public function test_an_invoiced_order_that_is_not_stripe_still_saves_the_choice(): void
    {
        $id = $this->invoicedOrder('PDF');
        $form = PageForm::fields($this->page($id), 'ordre');
        $form['udskriv_til'] = 'email';
        $this->page($id, $form);
        $this->assertSame('email', $this->stored($id));
    }
}
