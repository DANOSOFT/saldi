<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/docsIncludes/docFileFunc.php';

/**
 * docFileUrl() and docFileResolve() decide which file docFile.php hands out, so a mistake here
 * serves another tenant's documents or files outside the document folder.
 */
final class docFileFunc extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/docFileFunc_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/tenant_a/pulje', 0777, true);
        mkdir($this->root . '/tenant_b/pulje', 0777, true);
        file_put_contents($this->root . '/tenant_a/pulje/invoice.pdf', '%PDF-1.4');
        file_put_contents($this->root . '/tenant_a/pulje/notes.php', '<?php');
        file_put_contents($this->root . '/tenant_b/pulje/other.pdf', '%PDF-1.4');
        file_put_contents($this->root . '/secret.pdf', '%PDF-1.4');
    }

    protected function tearDown(): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            ($item->isDir() && !$item->isLink()) ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function testUrlForAFileInTheTenantsDocumentFolder(): void
    {
        self::assertSame(
            '../includes/docsIncludes/docFile.php?k=doc&f=finance%2F30%2F141%2Fbilag.pdf',
            docFileUrl('../bilag/saldi_x/finance/30/141/bilag.pdf', '../bilag', 'saldi_x')
        );
    }

    public function testUrlCollapsesDoubleSlashesAsTheViewersDo(): void
    {
        self::assertSame(
            '../includes/docsIncludes/docFile.php?k=doc&f=pulje%2Fa.pdf',
            docFileUrl('../bilag/saldi_x//pulje/a.pdf', '../bilag/', 'saldi_x')
        );
    }

    public function testUrlForARenderedXmlPreview(): void
    {
        self::assertSame(
            '../includes/docsIncludes/docFile.php?k=temp&f=xml_view_0123456789abcdef0123456789abcdef.html',
            docFileUrl('../temp/saldi_x/xml_view_0123456789abcdef0123456789abcdef.html', '../bilag', 'saldi_x')
        );
    }

    public function testUrlEndsInTheFileExtension(): void
    {
        $url = docFileUrl('../bilag/saldi_x/pulje/scan 1.pdf', '../bilag', 'saldi_x');
        self::assertSame('pdf', strtolower(substr($url, strrpos($url, '.') + 1)));
    }

    public function testAnotherTenantsPathIsLeftUnchanged(): void
    {
        self::assertSame(
            '../bilag/saldi_y/pulje/a.pdf',
            docFileUrl('../bilag/saldi_y/pulje/a.pdf', '../bilag', 'saldi_x')
        );
    }

    public function testNoTenantLeavesThePathUnchanged(): void
    {
        self::assertSame('../bilag//pulje/a.pdf', docFileUrl('../bilag//pulje/a.pdf', '../bilag', ''));
    }

    public function testResolvesAFileInsideTheRoot(): void
    {
        self::assertSame(
            realpath($this->root . '/tenant_a/pulje/invoice.pdf'),
            docFileResolve($this->root . '/tenant_a', 'pulje/invoice.pdf', ['pdf'])
        );
    }

    public function testRefusesTraversalIntoAnotherTenant(): void
    {
        self::assertNull(docFileResolve($this->root . '/tenant_a', '../tenant_b/pulje/other.pdf', ['pdf']));
    }

    public function testRefusesTraversalOutOfTheDocumentRoot(): void
    {
        self::assertNull(docFileResolve($this->root . '/tenant_a', '../secret.pdf', ['pdf']));
    }

    public function testRefusesASymlinkPointingOutOfTheRoot(): void
    {
        $link = $this->root . '/tenant_a/pulje/link.pdf';
        if (!@symlink($this->root . '/tenant_b/pulje/other.pdf', $link)) {
            self::markTestSkipped('Symlinks are not available here');
        }
        self::assertNull(docFileResolve($this->root . '/tenant_a', 'pulje/link.pdf', ['pdf']));
    }

    public function testRefusesAnExtensionThatIsNotAllowed(): void
    {
        self::assertNull(docFileResolve($this->root . '/tenant_a', 'pulje/notes.php', ['pdf']));
    }

    public function testRefusesADirectory(): void
    {
        self::assertNull(docFileResolve($this->root . '/tenant_a', 'pulje', ['pdf']));
    }

    public function testRefusesAMissingFileEmptyPathAndNullByte(): void
    {
        self::assertNull(docFileResolve($this->root . '/tenant_a', 'pulje/missing.pdf', ['pdf']));
        self::assertNull(docFileResolve($this->root . '/tenant_a', '', ['pdf']));
        self::assertNull(docFileResolve($this->root . '/tenant_a', "pulje/invoice.pdf\0.png", ['pdf', 'png']));
    }

    public function testRefusesWhenTheTenantFolderDoesNotExist(): void
    {
        self::assertNull(docFileResolve($this->root . '/tenant_missing', 'pulje/invoice.pdf', ['pdf']));
    }

    public function testContentTypes(): void
    {
        self::assertSame('application/pdf', docFileContentType('pdf'));
        self::assertSame('image/jpeg', docFileContentType('jpeg'));
        self::assertSame('application/octet-stream', docFileContentType('php'));
    }
}
