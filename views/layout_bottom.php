<?php
declare(strict_types=1);
?>
</main><?php clearstatcache(true,DB_FILE);$dbFileSize=is_file(DB_FILE)?(int)filesize(DB_FILE):0;?><footer><div class="wrap"><?=h($appName)?> · <?=h(APP_VERSION)?> · SQLite · <?=h(basename(DB_FILE))?> (<?=h(fileSizeText($dbFileSize))?>) · <?=date('Y')?><?php if(APP_SOURCE_URL!==''):?> · <a href="<?=h(APP_SOURCE_URL)?>" rel="noopener">Lähdekoodi (AGPL v3)</a><?php endif;?></div></footer>
</body></html>
