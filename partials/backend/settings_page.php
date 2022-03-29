<script>
    function qpss3_connect(btn)
    {
        jQuery(btn).attr('disabled', 'disabled');
        window.location.href = "<?= $authUrl ?>";
        return false;
    }
</script>

<div class="wrap zigwidgetclass-admin">
    <h2>myPolice QPSDB</h2>
    <p>myPolice QPSDB is a plugin that lets you send to and update videos on YouTube with a given title, description and privacy setting.</p>

    <?php if (empty($accessToken) || empty($refreshToken)) : ?>
        <p>
            Status:
            <span style="color:red">NOT CONNECTED</span>
            <button type="button" onclick="qpss3_connect(this);" class="button">
                Connect to
                <img src="<?= plugins_url("assets/images/yt_logo_rgb_light.png", __DIR__ . '/../../index.php') ?>?20220330" width="60" />
            </button>
        </p>
    <?php else : ?>
        <p>
            Status:
            <span style="color:green">CONNECTED</span>
        </p>
    <?php endif; ?>
</div>