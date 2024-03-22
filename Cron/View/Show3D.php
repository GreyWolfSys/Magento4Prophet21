<script>;
    var form = document.createElement("form");
    $(form).attr("id", "cardinalform").attr("name", "frmLaunch").attr("action", '<?php echo $acsurl; ?>').attr("method", "post")
    <?php if($isiframe){ ?>
        $(form).attr("target", 'SecureIframe');
    <?php } ?>
    $(form).append('<input type="hidden" name="PaReq" value="<?php echo $pareq; ?>" />');
    $(form).append('<input type="hidden" name="TermUrl" value="<?php echo $urlreturn; ?>" />');
    $(form).append('<input type="hidden" name="MD" value="<?php echo $md; ?>" />');

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
</script>
 <?php if($isiframe){ ?>
        <iframe frameBorder='0' id='cenposPaySecureId' name='SecureIframe' class='ïframecenpos'  width='100%' height='<?php echo $height; ?>'></iframe>"
 <?php } ?>
