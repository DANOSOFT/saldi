<?php
class SQLConverter {
    private $content;
    private $debug = [];
    
    public function __construct($file) {
        $this->content = file_get_contents($file);
        if ($this->content === false) {
            throw new Exception("Could not read file: $file");
        }
    }
    
    public function convert() {
        // Remove MySQL specific statements
        $this->removeSetStatements();
        $this->removeEngineStatements();
        
        // Convert data types
        $this->convertDataTypes();
        
        // Fix auto increment syntax
        $this->convertAutoIncrement();
        
        // Fix case-sensitivity issues with column names
        $this->ensureConsistentColumnCasing();
        
        // Fix index definitions
        $this->fixIndexDefinitions();
        
        // Handle quoting differences
        $this->convertQuoting();
        
        return $this->content;
    }
    
    public function getDebugInfo() {
        return $this->debug;
    }
    
    private function removeSetStatements() {
        $this->content = preg_replace('/SET NAMES.+?;/i', '', $this->content);
        $this->content = preg_replace('/SET time_zone.+?;/i', '', $this->content);
        $this->content = preg_replace('/SET foreign_key_checks.+?;/i', '', $this->content);
        $this->content = preg_replace('/SET sql_mode.+?;/i', '', $this->content);
    }
    
    private function removeEngineStatements() {
        $this->content = preg_replace('/\)\s*ENGINE=\w+/i', ')', $this->content);
        $this->content = preg_replace('/DEFAULT CHARSET=\w+/i', '', $this->content);
    }
    
    private function convertDataTypes() {
        // int/bigint with display width to simple int/bigint
        $this->content = preg_replace('/int\(\d+\)/i', 'integer', $this->content);
        $this->content = preg_replace('/bigint\(\d+\)/i', 'bigint', $this->content);
        
        // varchar with length preserved
        $this->content = preg_replace('/varchar\((\d+)\)/i', 'varchar($1)', $this->content);
        
        // Convert other MySQL-specific types
        $this->content = str_ireplace('datetime', 'timestamp', $this->content);
        $this->content = str_ireplace('longtext', 'text', $this->content);
        $this->content = str_ireplace('mediumtext', 'text', $this->content);
    }
    
    private function convertAutoIncrement() {
        // Find AUTO_INCREMENT columns and convert to PostgreSQL serial
        $this->content = preg_replace_callback(
            '/`(\w+)`\s+\w+(?:\(\d+\))?\s+NOT\s+NULL\s+AUTO_INCREMENT/i',
            function ($matches) {
                $columnName = $matches[1];
                return "\"$columnName\" serial NOT NULL";
            },
            $this->content
        );
    }
    
    private function ensureConsistentColumnCasing() {
        // First, find the actual case of column names that might be referenced in indexes
        $columnCases = [];
        
        // Store specific case for kobsdate to fix the error
        $kobsdatePattern = '/"([Kk][Oo][Bb][Ss][Dd][Aa][Tt][Ee])"\s+(?:timestamp|date|datetime|integer|bigint|text|varchar)/i';
        if (preg_match($kobsdatePattern, $this->content, $matches)) {
            $columnCases['kobsdate'] = $matches[1]; // Save the exact case used in table definition
            $this->debug[] = "Found KobsDate column: " . $matches[1];
        } else {
            $columnCases['kobsdate'] = 'kobsdate'; // Default fallback
        }
        
        // Check specifically for voucheruse table
        $voucherTablePattern = '/CREATE\s+TABLE\s+"voucheruse"/i';
        if (preg_match($voucherTablePattern, $this->content)) {
            if (preg_match_all('/"([^"]+)"\s+/', $this->content, $columnMatches)) {
                foreach ($columnMatches[1] as $col) {
                    if (strtolower($col) === 'kobsdate') {
                        $columnCases['kobsdate'] = $col;
                        $this->debug[] = "Found exact column case in voucheruse table: $col";
                        break;
                    }
                }
            }
        }
        
        // Fix specifically the problematic index
        $this->content = preg_replace(
            '/CREATE\s+INDEX\s+"batch_kob_kobsdate_idx"\s+ON\s+"voucheruse"\s+\("(?:[Kk][Oo][Bb][Ss][Dd][Aa][Tt][Ee])"\)/i',
            'CREATE INDEX "batch_kob_kobsdate_idx" ON "voucheruse" ("' . $columnCases['kobsdate'] . '")',
            $this->content
        );
        
        // Fix any other references to this column
        $this->content = preg_replace_callback(
            '/"([Kk][Oo][Bb][Ss][Dd][Aa][Tt][Ee])"/i',
            function ($matches) use ($columnCases) {
                return '"' . $columnCases['kobsdate'] . '"';
            },
            $this->content
        );
    }
    
    private function fixIndexDefinitions() {
        // Move all indexes outside of table definitions
        preg_match_all('/CREATE\s+TABLE\s+"([^"]+)"\s*\((.+?)\);/is', $this->content, $tables, PREG_SET_ORDER);
        
        foreach ($tables as $table) {
            $tableName = $table[1];
            $tableContent = $table[2];
            
            // Extract UNIQUE and KEY definitions
            preg_match_all('/,\s*((?:UNIQUE\s+)?KEY\s+`([^`]+)`\s*\(([^)]+)\))/i', $tableContent, $indexes, PREG_SET_ORDER);
            
            if (count($indexes) > 0) {
                $newTableContent = preg_replace('/,\s*(?:UNIQUE\s+)?KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $tableContent);
                $createTableStmt = 'CREATE TABLE "' . $tableName . '" (' . $newTableContent . ');';
                
                // Build separate CREATE INDEX statements
                $indexStatements = [];
                foreach ($indexes as $index) {
                    $indexType = (strpos(strtoupper($index[1]), 'UNIQUE') === 0) ? 'UNIQUE INDEX' : 'INDEX';
                    $indexName = $index[2];
                    $indexColumns = str_replace('`', '"', $index[3]);
                    
                    $indexStatements[] = "CREATE $indexType \"$indexName\" ON \"$tableName\" ($indexColumns);";
                }
                
                // Replace the original CREATE TABLE with the new one and add the index statements
                $replacement = $createTableStmt . "\n" . implode("\n", $indexStatements);
                $this->content = str_replace('CREATE TABLE "' . $tableName . '" (' . $tableContent . ');', $replacement, $this->content);
            }
        }
    }
    
    private function convertQuoting() {
        // Replace MySQL backtick quotes with PostgreSQL double quotes
        $this->content = preg_replace('/`([^`]+)`/', '"$1"', $this->content);
    }
    
    public function saveToFile($outputFile) {
        return file_put_contents($outputFile, $this->content);
    }
}

try {
    // Check if a file path was provided in the request
    $inputFile = isset($_POST['file']) ? $_POST['file'] : null;
    
    // For command-line use
    if (empty($inputFile) && isset($argv) && count($argv) > 1) {
        $inputFile = $argv[1];
    }
    
    if (empty($inputFile)) {
        echo "Please provide a file to convert.";
        exit(1);
    }
    
    $converter = new SQLConverter($inputFile);
    $converted = $converter->convert();
    
    $outputFile = $inputFile . '.pgsql';
    if ($converter->saveToFile($outputFile)) {
        echo "Conversion completed. PostgreSQL SQL saved to $outputFile\n";
        
        if (isset($_GET['debug'])) {
            echo "<pre>Debug information:\n";
            print_r($converter->getDebugInfo());
            echo "</pre>";
        }
    } else {
        echo "Error saving the converted SQL to $outputFile\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>