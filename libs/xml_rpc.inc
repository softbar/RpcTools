<?php 

function xmlrpc_encode_request($methodName,$args) {
    $req = '<?xml version="1.0" encoding="UTF-8" ?>';
    $params = '';
    foreach($args as $arg) {
        $parm = XmlRPC_Parm::encode($arg);
        $params .= sprintf('<param><value>%s</value></param>',$parm);
    }
    $req .= "\n<methodCall><methodName>$methodName</methodName><params>$params</params></methodCall>";
    return $req;
}
function xmlrpc_decode($xmlStr){
	$parseValue=null;
	$response = array();
    $parseValue=function($valueStruct)use(&$parseValue) {
        switch(true) {
            case count($valueStruct->struct) > 0:
                $value = [];
                foreach($valueStruct->struct->member as $member) {
                    $name = (string)$member->name;
                    $memberValue = $parseValue($member->value);
                    $value[$name] = $memberValue;
                }
            	return $value;
                break;
            case count($valueStruct->array) > 0:
                $value = array();
                foreach($valueStruct->array->data->value as $arrayValue) {
                    $value[] = $parseValue($arrayValue);
                }
                return $value;
                break;
            case count($valueStruct->i4) > 0:
                return (int)$valueStruct->i4;
            case count($valueStruct->int) > 0:
                return (int)$valueStruct->int;
            case count($valueStruct->boolean) > 0:
                return (boolean) $valueStruct->boolean;
            case count($valueStruct->string) > 0:
                return (string)$valueStruct->string;
            case count($valueStruct->double) > 0:
                return (double)$valueStruct->double;
            case count($valueStruct->dateTime) > 0:
                return (string)$valueStruct->dateTime;
            case count($valueStruct->base64) > 0:
                return (string)$valueStruct->base64;
        }
        $var=trim((string)$valueStruct);
        if(is_numeric($var))$var=is_float($var)?floatval($var):intval($var);
        elseif($var=='true'||$var=='false')$var=$var=='true';
        return $var;
    };  
    $xml = simplexml_load_string(trim($xmlStr));
    if (count($xml->fault) > 0) {
        $fault = $parseValue($xml->fault->value);
        return ['faultCode'=>$fault->faultCode, 'faultString'=>$fault->faultString];
    }
    if (count($xml->params->param) == 1)$scalar = true;
    foreach($xml->params->param as $param) {
        $valueStruct = $param->value;
        $value = $parseValue($valueStruct);
        if ($scalar)
            return $value;
        else
            $response[] = $value;
    }
    return $response;
}
 
/* PARMS */
class XmlRPC_Parm {
    private $value;

    public function __construct($value) {
        $this->value = $value;
    }
    public function getType() {
        switch(true) {
            case is_bool($this->value):
                return 'boolean';
            case is_int($this->value):
                return 'int';
            case is_float($this->value):
            case is_double($this->value):
                return 'double';
                break;
            case is_object($this->value):
                return 'struct';
            case is_bool($this->value):
                return 'boolean';
            case is_array($this->value):
                return 'array';
            default:
            case is_string($this->value):
                return 'string';
                break;
        }
    }
    public function getValue() {
        return $this->value;
    }
    protected function getFormattedValue() {
        return $this->value;
    }

    public function __toString() {
        return sprintf('<%1$s>%2$s</%1$s>',$this->getType(),$this->getFormattedValue());
    }
    /**
     *
     * @param mixed $arg
     * @return XmlRPC_Parm
     */
    public static function encode($arg) {
        switch(true) {
            case $arg instanceof XmlRPC_Parm:
                return $arg;
            case is_object($arg):
                return new XmlRPC_Struct($arg);
            case is_array($arg):
                return new XmlRPC_Array($arg);
            default:
            case is_bool($arg):
            case is_int($arg):
            case is_float($arg):
            case is_double($arg):
            case is_string($arg):
                return new XmlRPC_Parm($arg);
        }
    }
    public static function decode($param) {

    }
}
class XmlRPC_Struct extends XmlRPC_Parm{

    protected function getFormattedValue() {
        $result = '';
        foreach($this->getValue() as $name=>$value) {
            $parm = XmlRPC_Parm::encode($value);
            $result .= sprintf('<member><name>%s</name><value>%s</value></member>',$name,$parm);
        }
        return $result;
    }
    public function getType() {
        return 'struct';
    }
}
class XmlRPC_Array extends XmlRPC_Parm{

    protected function getFormattedValue() {
        $result = '<data>';
        foreach($this->getValue() as $value) {
            $parm = XmlRPC_Parm::encode($value);
            $result .= sprintf('<value>%s</value>',$parm);
        }
        return $result.'</data>';
    }
    public function getType() {
        return 'array';
    }
}
class XmlRPC_Date extends XmlRPC_Parm{

    protected function getFormattedValue() {
        return date('Ymd\TH:i:s',parent::getFormattedValue());
    }
    public function getType() {
        return 'dateTime.iso8601';
    }
}
class XmlRPC_Binary extends XmlRPC_Parm{

    protected function getFormattedValue() {
        return base64_encode(parent::getFormattedValue());
    }
    public function getType() {
        return 'base64';
    }
}

?>