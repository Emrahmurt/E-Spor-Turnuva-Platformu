</main>

<!-- Alt Bilgi (Footer) -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Hakkında -->
            <div class="footer-bolum">
                <a href="<?= SITE_URL ?>" class="footer-logo">
                    <i class="fas fa-gamepad"></i>
                    <span>E-SPOR<span class="logo-vurgu">TURNUVA</span></span>
                </a>
                <p class="footer-aciklama">Türkiye'nin en profesyonel e-spor turnuva platformu. Turnuvalara katıl, takımını kur, şampiyon ol!</p>
                <div class="footer-sosyal">
                    <a href="https://discord.com" target="_blank"><i class="fab fa-discord"></i></a>
                    <a href="https://x.com" target="_blank"><i class="fab fa-twitter"></i></a>
                    <a href="https://instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="https://twitch.tv" target="_blank"><i class="fab fa-twitch"></i></a>
                    <a href="https://youtube.com" target="_blank"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <!-- Hızlı Linkler -->
            <div class="footer-bolum">
                <h4 class="footer-baslik">Hızlı Linkler</h4>
                <ul class="footer-linkler">
                    <li><a href="<?= SITE_URL ?>/turnuvalar.php">Turnuvalar</a></li>
                    <li><a href="<?= SITE_URL ?>/takimlar.php">Takımlar</a></li>
                    <li><a href="<?= SITE_URL ?>/oyuncular.php">Oyuncular</a></li>
                    <li><a href="<?= SITE_URL ?>/siralama.php">Sıralama</a></li>
                    <li><a href="<?= SITE_URL ?>/haberler.php">Haberler</a></li>
                </ul>
            </div>

            <!-- Oyunlar -->
            <div class="footer-bolum">
                <h4 class="footer-baslik">Oyunlar</h4>
                <ul class="footer-linkler">
                    <li><a href="<?= SITE_URL ?>/turnuvalar.php?oyun=valorant">Valorant</a></li>
                    <li><a href="<?= SITE_URL ?>/turnuvalar.php?oyun=cs2">CS2</a></li>
                    <li><a href="<?= SITE_URL ?>/turnuvalar.php?oyun=league-of-legends">League of Legends</a></li>
                    <li><a href="<?= SITE_URL ?>/turnuvalar.php?oyun=dota-2">Dota 2</a></li>
                    <li><a href="<?= SITE_URL ?>/turnuvalar.php?oyun=fortnite">Fortnite</a></li>
                </ul>
            </div>

            <!-- İletişim -->
            <div class="footer-bolum">
                <h4 class="footer-baslik">İletişim</h4>
                <ul class="footer-iletisim">
                    <li><i class="fas fa-envelope"></i> info@esporturnuva.com</li>
                    <li><i class="fas fa-phone"></i> +90 555 123 4567</li>
                    <li><i class="fas fa-map-marker-alt"></i> Ordu, Türkiye</li>
                </ul>
            </div>
        </div>

        <!-- Alt Çizgi -->
        <div class="footer-alt">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Tüm hakları saklıdır.</p>
            <div class="footer-alt-linkler">
                <a href="#">Gizlilik Politikası</a>
                <a href="#">Kullanım Şartları</a>
                <a href="#">Çerez Politikası</a>
            </div>
        </div>
    </div>
</footer>

<!-- Yukarı Çık Butonu -->
<button class="yukari-btn" id="yukariBtn" title="Yukarı Çık">
    <i class="fas fa-chevron-up"></i>
</button>

<!-- JavaScript -->
<script src="<?= JS_URL ?>/ana.js?v=<?= filemtime(ASSETS_PATH . 'js' . DIRECTORY_SEPARATOR . 'ana.js') ?>"></script>
<?php if (isset($ekstraJS)): ?>
    <script src="<?= JS_URL ?>/<?= $ekstraJS ?>?v=<?= filemtime(ASSETS_PATH . 'js' . DIRECTORY_SEPARATOR . $ekstraJS) ?>"></script>
<?php endif; ?>
</body>
</html>
