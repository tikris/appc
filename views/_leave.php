<?php
// Lien « Oublier cet appareil » du pied de page, pour un participant entré avec un code.
if ($me !== null) {
    $footerExtra = '<form method="post" action="' . h(url($in->slug . '/quitter')) . '" class="inline">'
        . \Appc\Csrf::field()
        . '<button type="submit" class="link-btn">Oublier cet appareil</button></form>';
}
