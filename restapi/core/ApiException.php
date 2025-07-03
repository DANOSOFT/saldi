<?php
class ApiException extends Exception 
{
    private $statusCode;
    
    public function __construct($message, $statusCode = 500, $previous = null) 
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }
    
    public function getStatusCode() 
    {
        return $this->statusCode;
    }
}