<?php
/**
 * Optional base class for objects written by xmlStreamWriter.
 *
 * Subclasses list attributes per property in $xmlAttributes. Objects that do not extend this class
 * are still written, just without any attributes.
 */
class xmlNode {
    /**
     * Attributes to put on the element written for a property, keyed by property name.
     *
     * @var array<string,array<string,string>> property name => [attribute name => attribute value]
     */
    protected array $xmlAttributes = [];

    /**
     * @return array<string,string> attribute name => attribute value, empty when the property has none
     */
    public function getXmlAttributes(string $property): array {
        return $this->xmlAttributes[$property] ?? [];
    }
}

/**
 * Marks a Traversable that xmlStreamWriter writes to a temporary file first, and copies into the document
 * when it reaches the property holding it.
 *
 * Properties of the same object that are written before it can then hold values that are only known once
 * the collection has been iterated, e.g. its item count (see xmlStreamWriter, Closure values).
 */
interface xmlBufferedCollection {
}

/**
 * Streams an object tree to an XML file with XMLWriter, so no DOM tree is held in memory.
 *
 * Each public property becomes a child element named after the property:
 * - scalar: element with text content (bool is written as true/false, null is skipped)
 * - object: nested element, written recursively
 * - array/Traversable: one element per item, so a generator can stream large collections
 * - Closure: called when the property is reached, and its result is written as above
 * Attributes come from xmlNode::getXmlAttributes() and are only looked up when the owning object
 * extends xmlNode.
 *
 * A Traversable that implements xmlBufferedCollection is iterated before the other properties of its owner
 * are written, into a temporary file next to the output file, and copied in at its place afterwards. A
 * Closure property written before it is therefore called after the collection has been iterated.
 */
class xmlStreamWriter {
    private const INDENT = ' ';
    private const COPY_CHUNK = 1048576;

    private XMLWriter $writer;
    private string $filePath;
    private int $written = 0;
    /** Number of currently open elements. */
    private int $depth = 0;
    /**
     * Children written so far inside the open element at each depth.
     *
     * @var array<int,int>
     */
    private array $childCount = [0];
    /** True while a collection is being written to its temporary file. */
    private bool $buffering = false;

    /**
     * @param string $filePath file to write, overwritten if it exists
     * @throws Exception when the file cannot be opened
     */
    public function __construct(string $filePath) {
        $this->filePath = $filePath;
        $this->writer = new XMLWriter();
        if (!$this->writer->openUri($filePath)) {
            throw new Exception("Unable to open file for writing: " . $filePath);
        }
        $this->writer->setIndent(true);
        $this->writer->setIndentString(self::INDENT);
    }

    /**
     * Writes the whole document and closes the file.
     *
     * @param object $root object whose properties become the children of the root element
     * @param string|null $rootName name of the root element; defaults to the root object's class name
     * @param array<string,string> $rootAttributes attributes for the root element, e.g. xmlns declarations
     */
    public function writeDocument(object $root, ?string $rootName = null, array $rootAttributes = []): void {
        $rootName ??= $root::class;
        $this->writer->startDocument('1.0', 'UTF-8');
        $this->startElement($rootName, $rootAttributes);
        $this->writeProperties($root);
        $this->endElement();
        $this->writer->endDocument();
        $this->writer->flush();
    }

    /**
     * @param array<string,string> $attributes
     */
    private function startElement(string $name, array $attributes): void {
        $this->childCount[$this->depth]++;
        $this->depth++;
        $this->childCount[$this->depth] = 0;
        $this->writer->startElement($name);
        foreach ($attributes as $attrName => $attrValue) {
            $this->writer->writeAttribute($attrName, $attrValue);
        }
    }

    private function endElement(): void {
        $this->writer->endElement();
        $this->depth--;
    }

    private function writeProperties(object $object): void {
        // Called from outside the object, so get_object_vars() only returns public properties.
        $properties = get_object_vars($object);
        $buffers = [];
        try {
            if (!$this->buffering) {
                foreach ($properties as $property => $value) {
                    if ($value instanceof xmlBufferedCollection && $value instanceof Traversable) {
                        $buffers[$property] = $this->bufferToFile($property, $value, $this->attributesFor($object, $property));
                    }
                }
            }
            $remaining = array_keys($properties);
            foreach ($properties as $property => $value) {
                array_shift($remaining);
                if (isset($buffers[$property])) {
                    $isLast = !$this->anyWillWrite($properties, $remaining);
                    $this->copyBuffered($buffers[$property], $isLast);
                    continue;
                }
                $this->writeValue($property, $value, $this->attributesFor($object, $property));
            }
        } finally {
            foreach ($buffers as $buffer) {
                if (is_file($buffer['path'])) {
                    unlink($buffer['path']);
                }
            }
        }
    }

    /**
     * @return array<string,string>
     */
    private function attributesFor(object $object, string $property): array {
        return $object instanceof xmlNode ? $object->getXmlAttributes($property) : [];
    }

    /**
     * Whether any of the given properties will produce output. Only used to place whitespace, so it errs on
     * the side of "yes" for values that can't be inspected without running them.
     *
     * @param array<string,mixed> $properties
     * @param array<int,string> $names properties to look at
     */
    private function anyWillWrite(array $properties, array $names): bool {
        foreach ($names as $name) {
            $value = $properties[$name];
            if ($value !== null && $value !== []) {
                return true;
            }
        }
        return false;
    }

    /**
     * Writes a collection to a temporary file, indented as if it sat at the current position of the document.
     *
     * @param array<string,string> $attributes
     * @return array{path:string,offset:int,empty:bool} temporary file, where the items start in it, and
     *                                                  whether nothing was written
     */
    private function bufferToFile(string $name, Traversable $items, array $attributes): array {
        $path = tempnam(dirname($this->filePath), 'xmlpart');
        if ($path === false) {
            throw new Exception("Unable to create temporary file next to: " . $this->filePath);
        }
        try {
            return $this->writeFragment($path, $name, $items, $attributes);
        } catch (Throwable $e) {
            unlink($path);
            throw $e;
        }
    }

    /**
     * @param array<string,string> $attributes
     * @return array{path:string,offset:int,empty:bool} see bufferToFile()
     */
    private function writeFragment(string $path, string $name, Traversable $items, array $attributes): array {
        $fragment = new XMLWriter();
        if (!$fragment->openUri($path)) {
            throw new Exception("Unable to open file for writing: " . $path);
        }
        $fragment->setIndent(true);
        $fragment->setIndentString(self::INDENT);
        // Open one placeholder element per open element of the real document, so libxml indents the items
        // to the right depth. They are never closed, and the file is only ever read from $offset.
        for ($level = 0; $level < $this->depth; $level++) {
            $fragment->startElement('x');
        }
        $fragment->writeRaw('');
        $fragment->flush();
        clearstatcache(true, $path);
        $offset = filesize($path);

        $mainWriter = $this->writer;
        $mainChildCount = $this->childCount;
        $this->writer = $fragment;
        $this->buffering = true;
        try {
            $this->writeValue($name, $items, $attributes);
        } finally {
            $this->writer = $mainWriter;
            $this->childCount = $mainChildCount;
            $this->buffering = false;
        }
        $fragment->flush();
        clearstatcache(true, $path);
        return ['path' => $path, 'offset' => $offset, 'empty' => filesize($path) <= $offset];
    }

    /**
     * Copies a buffered collection into the document at the current position.
     *
     * @param array{path:string,offset:int,empty:bool} $buffer from bufferToFile()
     * @param bool $isLast true when no more elements follow inside the open element
     */
    private function copyBuffered(array $buffer, bool $isLast): void {
        if ($buffer['empty']) {
            return;
        }
        $in = fopen($buffer['path'], 'rb');
        if ($in === false) {
            throw new Exception("Unable to read temporary file: " . $buffer['path']);
        }
        try {
            fseek($in, $buffer['offset']);
            // libxml only starts a new line before raw output when a sibling precedes it
            if ($this->childCount[$this->depth] === 0) {
                $this->writer->writeRaw("\n");
            }
            while (($chunk = fread($in, self::COPY_CHUNK)) !== false && $chunk !== '') {
                $this->writer->writeRaw($chunk);
            }
        } finally {
            fclose($in);
        }
        $this->childCount[$this->depth]++;
        // The copied block ends with a newline. After raw output libxml indents a following element but not a
        // closing tag, so that indentation is added here.
        if ($isLast) {
            $this->writer->writeRaw(str_repeat(self::INDENT, $this->depth - 1));
        }
    }

    /**
     * @param array<string,string> $attributes
     */
    private function writeValue(string $name, mixed $value, array $attributes): void {
        if ($value instanceof Closure) {
            $value = $value();
        }
        if ($value === null) {
            return;
        }
        if (is_array($value) || $value instanceof Traversable) {
            foreach ($value as $item) {
                $this->writeValue($name, $item, $attributes);
                if (++$this->written % 500 === 0) {
                    $this->writer->flush();
                }
            }
            return;
        }
        $this->startElement($name, $attributes);
        if (is_object($value)) {
            $this->writeProperties($value);
        } else {
            $this->writer->text(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        }
        $this->endElement();
    }
}
?>