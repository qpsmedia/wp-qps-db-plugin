<script>
    jQuery(document).ready(function ($) {
        qpsdb_reload();
    });

    function qpsdb_reload()
    {
        $('#qpsdb-status :button').attr('disabled', 'disabled');

        $.getJSON(ajaxurl + '?action=qpsdb_yt_get_status&id=<?= $post->ID ?>', function(response) {
            console.log(response);
            if (!response.success) {
                $('#qpsdb-status').html("\
                    <div class='notice notice-error'>\
                        " + response.message + "\
                    </div>\
                ");

                return;
            }

            if (response.message.status == "error") {
                $('#qpsdb-status').html("\
                    <div class='notice notice-error' style='margin-left:0'>Upload failed.</div>\
                    <input type='button' class='button button-small' value='Try Again' onclick='return qpsdb_upload(<?= $post->ID ?>);' />\
                ");
            } else if (response.message.status == "uploaded") {
                $('#qpsdb-status').html("\
                    <div class='notice notice-success' style='margin-left:0'>\
                        Uploaded. <a href='https://www.youtube.com/watch?v=" + response.message.id + "' target='_blank'>View here</a>\
                    </div>\
                ");
            } else if (response.message.status == "uploading") {
                $('#qpsdb-status').html("\
                    <div class='notice notice-info' style='margin-left:0'>\
                        Uploading. Please stand by.\
                    </div>\
                ");

                // Reload every 5 seconds while uploading.
                // Use setTimeout over setInterval as this line will be executed
                // again if the upload is still pending.
                setTimeout(qpsdb_reload, 5000);
            } else {
                $('#qpsdb-status').html("\
                    <div class='notice notice-info' style='margin-left:0'>\
                        Not uploaded.\
                    </div>\
                    <input type='button' class='button button-small' value='Upload with QPSDB' onclick='return qpsdb_upload(<?= $post->ID ?>);' />\
                ");
            }
        });
    }

    function qpsdb_upload()
    {
        $('#qpsdb-status :button').attr('disabled', 'disabled');

        $.ajax({
            url: ajaxurl + "?action=qpsdb_yt_upload&id=<?= $post->ID ?>",
            method: "POST",
            data: {
                qpsdb_yt_settings: {
                    privacy: '<?= \QPS\DB\YouTubePost::PRIVACY_UNLISTED ?>',
                    title: $('#attachment-details-two-column-title').val(),
                    description: $('#attachment-details-two-column-description').val()
                }
            },
            dataType: "json",
            error: function(jqXHR, textStatus, errorThrown) {
                alert("An unknown error occurred: " + errorThrown);
                $('#qpsdb-status :button').removeAttr('disabled');
            },
            success: function(data, textStatus, jqXHR) {
                if (!data.success) {
                    alert("Error: " + data.message);
                    $('#qpsdb-status :button').removeAttr('disabled');
                } else {
                    qpsdb_reload();
                }
            }
        });
        return false;
    }
</script>
<div id="qpsdb-status">

</div>