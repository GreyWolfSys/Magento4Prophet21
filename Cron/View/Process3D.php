<?php 
    require_once '../CenposConnector.php';
    if (session_status() == PHP_SESSION_NONE)
          session_start();
        
    $Md = (isset($_SESSION["CardinalTransactionID"])) ? $_SESSION["CardinalTransactionID"] : "";
    $Transaction = (isset($_SESSION["CardinalTransactionData"])) ? $_SESSION["CardinalTransactionData"] : "";
    
    $MdSend = (isset($_REQUEST["MD"])) ? $_REQUEST["MD"] : "";
    $Callback = (isset($_REQUEST["callback"])) ? $_REQUEST["callback"] : "";
    $Pares = (isset($_REQUEST["Pares"])) ? $_REQUEST["Pares"] : "";
    
    $Response = array();
    $Response["callback"] = $Callback;
    if($Md != $MdSend){
        $Response["msg"] = "";
    }else{
        CenposConnector::Init();
        $Cenpos = new CenposConnector();
        $Transaction = json_decode($Transaction);
        $Transaction->SecureCode = "<SecureCode><TransactionId>" . $MdSend . "</TransactionId><PaRes>" .$Pares . "</PaRes></SecureCode>";
        $Response["msg"] = $Cenpos->UseCryptoToken($Transaction);
    }
?>
<script src="https://code.jquery.com/jquery-1.10.1.min.js"></script>
<script>
    jQuery.noConflict();
    var $3 = null;
    jQuery().ready(function ($$){
        $3 = $$;
        var cardinalresponse =  $3.parseJSON('<?php echo json_encode($Response); ?>');
        
        if(isDefined(cardinalresponse.callback)) parent[cardinalresponse.callback](cardinalresponse.msg);
        else{
            var urlcustomdefa = cardinalresponse.callback.indexOf("?");
            window.location.href =  cardinalresponse.callback + ((urlcustomdefa > 0) ? "&" : "?") + "datasend=" + JSON.stringify(cardinalresponse.msg);
        }
    });
    function isNullorEmpty(value) {
            if (value !== undefined && value !== "" && value !== null) return false;
            else return true;
    }
    
    function isDefined(variable) {;
        if (typeof (parent[variable]) != "undefined") return true;
        if (typeof (parent.variable) == "function") return true;
        return false;
    }
    
</script>   