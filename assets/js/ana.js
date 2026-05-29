/**
 * E-Spor Turnuva Platformu - Ana JavaScript
 */

// Sayfa yüklenmeden önce tema uygula (flash engelleme)
(function() {
    const kayitliTema = localStorage.getItem('espor-tema') || 'dark';
    document.documentElement.setAttribute('data-tema', kayitliTema);
})();

document.addEventListener('DOMContentLoaded', function() {
    // =====================================================
    // 0. TEMA VE FAVICON DEĞİŞTİRİCİ
    // =====================================================
    const temaToggle = document.getElementById('temaToggle');
    
    // Favicon güncelleme fonksiyonu
    function faviconGuncelle(tema) {
        const favicon = document.querySelector('link[rel="icon"]');
        if (favicon) {
            const currentHref = favicon.getAttribute('href');
            let newHref;
            if (tema === 'light') {
                newHref = currentHref.replace('favicon.svg', 'favicon-light.svg');
            } else {
                newHref = currentHref.replace('favicon-light.svg', 'favicon.svg');
            }
            favicon.setAttribute('href', newHref);
        }
    }

    // İlk yüklemede favicon ayarla
    const baslangicTemasi = document.documentElement.getAttribute('data-tema') || 'dark';
    faviconGuncelle(baslangicTemasi);
    
    if (temaToggle) {
        // İkon güncelle
        function temaIkonGuncelle() {
            const tema = document.documentElement.getAttribute('data-tema');
            const ikon = temaToggle.querySelector('i');
            if (ikon) {
                ikon.className = tema === 'light' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }
        temaIkonGuncelle();
        
        temaToggle.addEventListener('click', function() {
            const mevcutTema = document.documentElement.getAttribute('data-tema');
            const yeniTema = mevcutTema === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-tema', yeniTema);
            localStorage.setItem('espor-tema', yeniTema);
            temaIkonGuncelle();
            faviconGuncelle(yeniTema);
        });
    }

    // =====================================================
    // 1. NAVBAR SCROLL EFEKT
    // =====================================================
    const navbar = document.getElementById('navbar');
    let lastScroll = 0;

    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }

        lastScroll = currentScroll;
    });

    // =====================================================
    // 2. MOBİL MENÜ
    // =====================================================
    const menuToggle = document.getElementById('menuToggle');
    const navMenu = document.getElementById('navMenu');

    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('acik');
            navMenu.classList.toggle('acik');
            document.body.style.overflow = navMenu.classList.contains('acik') ? 'hidden' : '';
        });

        // Menü dışına tıklanınca kapat
        document.addEventListener('click', (e) => {
            if (!menuToggle.contains(e.target) && !navMenu.contains(e.target)) {
                menuToggle.classList.remove('acik');
                navMenu.classList.remove('acik');
                document.body.style.overflow = '';
            }
        });
    }

    // =====================================================
    // 3. BİLDİRİM DROPDOWN
    // =====================================================
    const bildirimBtn = document.getElementById('bildirimBtn');
    const bildirimDropdown = document.getElementById('bildirimDropdown');

    if (bildirimBtn && bildirimDropdown) {
        bildirimBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            bildirimDropdown.classList.toggle('acik');
            // Kullanıcı dropdown'ı kapat
            const kullaniciDropdown = document.getElementById('kullaniciDropdown');
            if (kullaniciDropdown) kullaniciDropdown.classList.remove('acik');
            
            // Bildirimleri yükle
            bildirimleriYukle();
        });
    }

    // =====================================================
    // 4. KULLANICI DROPDOWN
    // =====================================================
    const kullaniciBtn = document.getElementById('kullaniciBtn');
    const kullaniciDropdown = document.getElementById('kullaniciDropdown');

    if (kullaniciBtn && kullaniciDropdown) {
        kullaniciBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            kullaniciDropdown.classList.toggle('acik');
            // Bildirim dropdown'ı kapat
            if (bildirimDropdown) bildirimDropdown.classList.remove('acik');
        });
    }

    // Dışarı tıklanınca dropdown'ları kapat
    document.addEventListener('click', () => {
        if (bildirimDropdown) bildirimDropdown.classList.remove('acik');
        if (kullaniciDropdown) kullaniciDropdown.classList.remove('acik');
    });

    // =====================================================
    // 5. YUKARI ÇIK BUTONU
    // =====================================================
    const yukariBtn = document.getElementById('yukariBtn');

    if (yukariBtn) {
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                yukariBtn.classList.add('gorunur');
            } else {
                yukariBtn.classList.remove('gorunur');
            }
        });

        yukariBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // =====================================================
    // 6. ANİMASYON GÖZLEMCI (Intersection Observer)
    // =====================================================
    const animasyonElementleri = document.querySelectorAll('.fade-in, .slide-in-left, .scale-in');
    
    if (animasyonElementleri.length > 0) {
        const gozlemci = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('gorunur');
                    }, index * 100);
                    gozlemci.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        animasyonElementleri.forEach(el => gozlemci.observe(el));
    }

    // =====================================================
    // 7. SAYAÇ ANİMASYONU
    // =====================================================
    const sayaclar = document.querySelectorAll('[data-sayac]');
    
    if (sayaclar.length > 0) {
        const sayacGozlemci = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    sayacAnimasyonu(entry.target);
                    sayacGozlemci.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        sayaclar.forEach(el => sayacGozlemci.observe(el));
    }

    function sayacAnimasyonu(element) {
        const hedef = parseInt(element.getAttribute('data-sayac'));
        const sure = 2000;
        const adim = hedef / (sure / 16);
        let mevcut = 0;

        const interval = setInterval(() => {
            mevcut += adim;
            if (mevcut >= hedef) {
                mevcut = hedef;
                clearInterval(interval);
            }
            element.textContent = Math.floor(mevcut).toLocaleString('tr-TR');
        }, 16);
    }

    // =====================================================
    // 8. TAB MENÜ
    // =====================================================
    const tabLinkler = document.querySelectorAll('.tab-link');
    
    tabLinkler.forEach(link => {
        link.addEventListener('click', function(e) {
            const hedefId = this.getAttribute('data-tab');
            
            // data-tab yoksa normal link davranışına izin ver (sayfa yenilenir)
            if (!hedefId) return;
            
            e.preventDefault();
            
            // Tüm tab'ları deaktif et
            this.closest('.tab-menu').querySelectorAll('.tab-link').forEach(l => l.classList.remove('aktif'));
            document.querySelectorAll('.tab-icerik').forEach(t => t.classList.remove('aktif'));
            
            // Seçili tab'ı aktif et
            this.classList.add('aktif');
            const hedef = document.getElementById(hedefId);
            if (hedef) hedef.classList.add('aktif');
        });
    });

    // =====================================================
    // 9. ALERT KAPATMA
    // =====================================================
    document.querySelectorAll('.alert-kapat').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.alert').remove();
        });
    });

    // =====================================================
    // 10. FORM DOĞRULAMA
    // =====================================================
    document.querySelectorAll('form[data-dogrula]').forEach(form => {
        form.addEventListener('submit', function(e) {
            let gecerli = true;
            
            this.querySelectorAll('[required]').forEach(input => {
                if (!input.value.trim()) {
                    gecerli = false;
                    input.style.borderColor = 'var(--danger)';
                    
                    // Hata mesajı ekle
                    let hata = input.nextElementSibling;
                    if (!hata || !hata.classList.contains('form-hata')) {
                        hata = document.createElement('p');
                        hata.className = 'form-hata';
                        hata.textContent = 'Bu alan zorunludur.';
                        input.parentNode.insertBefore(hata, input.nextSibling);
                    }
                } else {
                    input.style.borderColor = '';
                    const hata = input.nextElementSibling;
                    if (hata && hata.classList.contains('form-hata')) {
                        hata.remove();
                    }
                }
            });

            if (!gecerli) {
                e.preventDefault();
            }
        });
    });

    // =====================================================
    // 11. AJAX YARDIMCI
    // =====================================================
    window.ajaxIstek = async function(url, options = {}) {
        try {
            const varsayilan = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            const ayarlar = { ...varsayilan, ...options };
            const yanit = await fetch(url, ayarlar);
            
            if (!yanit.ok) throw new Error(`HTTP Hata: ${yanit.status}`);
            
            return await yanit.json();
        } catch (hata) {
            console.error('AJAX Hata:', hata);
            return null;
        }
    };

    // =====================================================
    // 12. BİLDİRİM YÜKLEME
    // =====================================================
    async function bildirimleriYukle() {
        const liste = document.getElementById('bildirimListe');
        if (!liste) return;

        try {
            const siteUrl = document.querySelector('meta[name="site-url"]')?.content || '';
            const veri = await ajaxIstek(siteUrl + '/api/bildirimler.php?son=5');
            
            if (veri && veri.bildirimler && veri.bildirimler.length > 0) {
                liste.innerHTML = veri.bildirimler.map(b => `
                    <a href="${b.link || '#'}" class="bildirim-item ${b.is_read ? '' : 'okunmamis'}" data-id="${b.id}">
                        <div class="bildirim-ikon">
                            <i class="fas fa-${b.type === 'tournament' ? 'trophy' : b.type === 'match' ? 'gamepad' : b.type === 'team' ? 'users' : 'bell'}"></i>
                        </div>
                        <div class="bildirim-icerik">
                            <p class="bildirim-mesaj">${b.title}</p>
                            <span class="bildirim-zaman">${b.zaman_once || ''}</span>
                        </div>
                    </a>
                `).join('');
            } else {
                liste.innerHTML = '<p class="bildirim-bos">Bildirim bulunmuyor.</p>';
            }
        } catch (e) {
            liste.innerHTML = '<p class="bildirim-bos">Bildirimler yüklenemedi.</p>';
        }
    }

    // =====================================================
    // 12.1 BİLDİRİM MERKEZİ EYLEMLERİ (AJAX)
    // =====================================================
    async function bildirimPostAksiyon(aksiyon, id = 0) {
        const siteUrl = document.querySelector('meta[name="site-url"]')?.content || '';
        const formData = new URLSearchParams();
        formData.append('action', aksiyon);
        if (id) {
            formData.append('id', id);
        }
        return await ajaxIstek(siteUrl + '/api/bildirimler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData.toString()
        });
    }

    function bildirimSayaclariniGuncelle(sayi) {
        // 1. Header çan badge'i
        const headerBadge = document.querySelector('.bildirim-badge');
        if (headerBadge) {
            if (sayi > 0) {
                headerBadge.textContent = sayi;
            } else {
                headerBadge.remove();
            }
        } else if (sayi > 0) {
            const bildirimBtn = document.getElementById('bildirimBtn');
            if (bildirimBtn) {
                const badge = document.createElement('span');
                badge.className = 'bildirim-badge';
                badge.textContent = sayi;
                bildirimBtn.appendChild(badge);
            }
        }
        
        // 2. Sidebar panel "Bildirimler" menüsü badge'i
        const sidebarBadge = document.querySelector('.dashboard-menu-link[href="?tab=bildirimler"] .badge');
        if (sidebarBadge) {
            if (sayi > 0) {
                sidebarBadge.textContent = sayi;
            } else {
                sidebarBadge.remove();
            }
        }
    }

    // Tekil Okundu / Okunmamış Yap
    document.querySelectorAll('.durum-degistir-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            const id = this.dataset.id;
            const isRead = parseInt(this.dataset.read) === 1;
            const yeniAksiyon = isRead ? 'okunmamis' : 'okundu';
            
            const sonuc = await bildirimPostAksiyon(yeniAksiyon, id);
            if (sonuc && sonuc.success) {
                const yeniRead = !isRead;
                this.dataset.read = yeniRead ? 1 : 0;
                this.title = yeniRead ? 'Okunmamış olarak işaretle' : 'Okundu olarak işaretle';
                this.querySelector('i').className = yeniRead ? 'fas fa-eye-slash' : 'fas fa-check';
                
                const kart = this.closest('.bildirim-kart');
                if (kart) {
                    if (yeniRead) {
                        kart.classList.remove('okunmamis');
                        kart.style.borderLeft = 'none';
                        kart.style.background = '';
                        kart.style.boxShadow = '';
                    } else {
                        kart.classList.add('okunmamis');
                        kart.style.borderLeft = '4px solid var(--accent)';
                        kart.style.background = 'rgba(255,255,255,0.02)';
                        kart.style.boxShadow = '0 0 15px rgba(var(--accent-rgb), 0.05)';
                    }
                }
                
                bildirimSayaclariniGuncelle(sonuc.okunmamis);
            }
        });
    });

    // Tekil Sil
    document.querySelectorAll('.sil-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            const id = this.dataset.id;
            if (confirm('Bu bildirimi silmek istediğinizden emin misiniz?')) {
                const sonuc = await bildirimPostAksiyon('sil', id);
                if (sonuc && sonuc.success) {
                    const kart = this.closest('.bildirim-kart');
                    if (kart) {
                        kart.style.opacity = '0';
                        kart.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            kart.remove();
                            const kalan = document.querySelectorAll('.bildirim-kart').length;
                            if (kalan === 0) {
                                window.location.reload();
                            }
                        }, 300);
                    }
                    bildirimSayaclariniGuncelle(sonuc.okunmamis);
                }
            }
        });
    });

    // Tümünü Okundu Yap
    const tumuOkunduBtn = document.getElementById('tumuOkunduBtn');
    if (tumuOkunduBtn) {
        tumuOkunduBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            const sonuc = await bildirimPostAksiyon('okundu');
            if (sonuc && sonuc.success) {
                window.location.reload();
            }
        });
    }

    // Tümünü Sil
    const tumuSilBtn = document.getElementById('tumuSilBtn');
    if (tumuSilBtn) {
        tumuSilBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            if (confirm('Tüm bildirimlerinizi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')) {
                const sonuc = await bildirimPostAksiyon('sil');
                if (sonuc && sonuc.success) {
                    window.location.reload();
                }
            }
        });
    }

    // Bildirime Tıklayıp Gitme
    document.querySelectorAll('.bildirim-link-click').forEach(link => {
        link.addEventListener('click', async function(e) {
            const id = this.dataset.id;
            const kart = this.closest('.bildirim-kart');
            if (kart && kart.classList.contains('okunmamis')) {
                e.preventDefault();
                const href = this.getAttribute('href');
                await bildirimPostAksiyon('okundu', id);
                window.location.href = href;
            }
        });
    });

    // Dropdown Bildirimlerine Tıklayıp Gitme (Delegasyon)
    const bildirimListe = document.getElementById('bildirimListe');
    if (bildirimListe) {
        bildirimListe.addEventListener('click', async function(e) {
            const item = e.target.closest('.bildirim-item.okunmamis');
            if (item) {
                e.preventDefault();
                const href = item.getAttribute('href');
                const id = item.dataset.id;
                if (id) {
                    await bildirimPostAksiyon('okundu', id);
                }
                window.location.href = href;
            }
        });
    }

    // =====================================================
    // 13. SMOOTH SCROLL
    // =====================================================
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', function(e) {
            const hedef = document.querySelector(this.getAttribute('href'));
            if (hedef) {
                e.preventDefault();
                hedef.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // =====================================================
    // 14. GÖRÜNTÜ LAZY LOAD
    // =====================================================
    if ('IntersectionObserver' in window) {
        const lazyImages = document.querySelectorAll('img[data-src]');
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            });
        });

        lazyImages.forEach(img => imageObserver.observe(img));
    }

    // =====================================================
    // 15. TOOLTIP
    // =====================================================
    document.querySelectorAll('[data-tooltip]').forEach(el => {
        el.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.dataset.tooltip;
            document.body.appendChild(tooltip);

            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 8) + 'px';
            tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
            
            this._tooltip = tooltip;
            requestAnimationFrame(() => tooltip.classList.add('gorunur'));
        });

        el.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
        });
    });

    // =====================================================
    // 16. ŞİFRE GÖSTER/GİZLE
    // =====================================================
    document.querySelectorAll('.sifre-goster-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.closest('.sifre-kapsayici')?.querySelector('input') || this.parentElement.querySelector('input');
            if (input) {
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    if (icon) {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    }
                } else {
                    input.type = 'password';
                    if (icon) {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                }
            }
        });
    });
});
