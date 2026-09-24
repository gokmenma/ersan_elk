/**
 * Global Topbar Search Engine
 * Ersan Elektrik - Multi-module Spotlight Search
 */
(function ($) {
    'use strict';

    var GlobalSearch = {
        input: null,
        mobileInput: null,
        clearBtn: null,
        spinner: null,
        mobileSpinner: null,
        dropdown: null,
        resultsContainer: null,
        mobileResultsContainer: null,
        categoriesContainer: null,
        footerInfo: null,
        totalCountBadge: null,

        debounceTimer: null,
        activeCategory: 'all',
        currentQuery: '',
        lastData: null,
        selectedIndex: -1,
        totalVisibleItems: 0,
        isOpen: false,

        init: function () {
            this.input = $('#global-search-input');
            this.mobileInput = $('#global-search-mobile-input');
            
            if (!this.input.length && !this.mobileInput.length) return;

            this.clearBtn = $('#global-search-clear');
            this.spinner = $('#global-search-spinner');
            this.mobileSpinner = $('#global-search-mobile-spinner');
            this.dropdown = $('#global-search-dropdown');
            this.resultsContainer = $('#global-search-results');
            this.mobileResultsContainer = $('#global-search-mobile-results');
            this.categoriesContainer = $('#global-search-categories');
            this.footerInfo = $('#gs-footer-info');
            this.totalCountBadge = $('#gs-total-count');

            this.bindEvents();
        },

        bindEvents: function () {
            var self = this;

            // Global Keyboard Shortcut: Ctrl+K / Cmd+K
            $(document).on('keydown', function (e) {
                var isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
                var isCmdOrCtrl = isMac ? e.metaKey : e.ctrlKey;

                if (isCmdOrCtrl && (e.key === 'k' || e.key === 'K')) {
                    e.preventDefault();
                    if (self.input.length && self.input.is(':visible')) {
                        self.input.focus();
                        self.input.select();
                        if (self.input.val().trim().length >= 1) {
                            self.openDropdown();
                        } else {
                            self.renderInitialSuggestions();
                        }
                    } else if (self.mobileInput.length) {
                        $('#page-header-search-dropdown').dropdown('show');
                        setTimeout(function () {
                            self.mobileInput.focus();
                        }, 100);
                    }
                } else if (e.key === 'Escape' && self.isOpen) {
                    e.preventDefault();
                    self.closeDropdown();
                    if (self.input.length) self.input.blur();
                }
            });

            // Desktop Input Events
            if (this.input.length) {
                this.input.on('focus', function () {
                    var val = $(this).val().trim();
                    if (val.length >= 1) {
                        if (self.lastData && self.currentQuery === val) {
                            self.openDropdown();
                        } else {
                            self.triggerSearch(val);
                        }
                    } else {
                        self.renderInitialSuggestions();
                    }
                });

                this.input.on('input', function () {
                    var val = $(this).val().trim();
                    if (val.length > 0) {
                        self.clearBtn.show();
                    } else {
                        self.clearBtn.hide();
                    }

                    clearTimeout(self.debounceTimer);
                    if (val.length >= 1) {
                        self.debounceTimer = setTimeout(function () {
                            self.triggerSearch(val);
                        }, 200);
                    } else {
                        self.renderInitialSuggestions();
                    }
                });

                // Keyboard Navigation inside input
                this.input.on('keydown', function (e) {
                    if (!self.isOpen) return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        self.moveSelection(1);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        self.moveSelection(-1);
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        self.activateSelected();
                    }
                });
            }

            // Mobile Input Events
            if (this.mobileInput.length) {
                this.mobileInput.on('input', function () {
                    var val = $(this).val().trim();
                    clearTimeout(self.debounceTimer);
                    if (val.length >= 1) {
                        self.debounceTimer = setTimeout(function () {
                            self.triggerMobileSearch(val);
                        }, 250);
                    } else {
                        self.renderMobileInitial();
                    }
                });
            }

            // Clear Button
            if (this.clearBtn.length) {
                this.clearBtn.on('click', function (e) {
                    e.stopPropagation();
                    self.input.val('').focus();
                    self.clearBtn.hide();
                    self.renderInitialSuggestions();
                });
            }

            // Category Tab Clicks
            if (this.categoriesContainer.length) {
                this.categoriesContainer.on('click', '.gs-cat-pill', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var cat = $(this).data('cat');
                    self.setCategory(cat);
                });

                // Mouse Wheel Yatay Kaydırma Desteği
                this.categoriesContainer.on('wheel', function (e) {
                    var oe = e.originalEvent;
                    if (oe.deltaY !== 0 || oe.deltaX !== 0) {
                        e.preventDefault();
                        var delta = oe.deltaY !== 0 ? oe.deltaY : oe.deltaX;
                        this.scrollLeft += delta * 0.8;
                    }
                });

                // Mouse Sürükleyerek Kaydırma (Drag to Scroll)
                var isDown = false;
                var startX = 0;
                var scrollLeftVal = 0;
                var hasDragged = false;

                this.categoriesContainer.on('mousedown', function (e) {
                    isDown = true;
                    hasDragged = false;
                    $(this).addClass('is-dragging');
                    startX = e.pageX - this.offsetLeft;
                    scrollLeftVal = this.scrollLeft;
                });

                $(document).on('mouseup', function () {
                    isDown = false;
                    if (self.categoriesContainer) {
                        self.categoriesContainer.removeClass('is-dragging');
                    }
                });

                this.categoriesContainer.on('mousemove', function (e) {
                    if (!isDown) return;
                    e.preventDefault();
                    var x = e.pageX - this.offsetLeft;
                    var walk = (x - startX) * 1.5;
                    if (Math.abs(walk) > 4) {
                        hasDragged = true;
                    }
                    this.scrollLeft = scrollLeftVal - walk;
                });
            }

            // Click outside to close
            $(document).on('click', function (e) {
                if (!$(e.target).closest('#global-search-wrapper').length) {
                    self.closeDropdown();
                }
            });

            // Prevent closing when clicking inside dropdown
            if (this.dropdown.length) {
                this.dropdown.on('click', function (e) {
                    e.stopPropagation();
                });
            }

            // Mouse hover on result items
            if (this.resultsContainer.length) {
                this.resultsContainer.on('mouseenter', '.gs-result-item', function () {
                    var index = $(this).data('index');
                    if (typeof index !== 'undefined') {
                        self.setSelectedIndex(index);
                    }
                });
            }
        },

        openDropdown: function () {
            if (this.dropdown.length) {
                this.dropdown.addClass('show').show();
                this.isOpen = true;
            }
        },

        closeDropdown: function () {
            if (this.dropdown.length) {
                this.dropdown.removeClass('show').hide();
                this.isOpen = false;
                this.selectedIndex = -1;
            }
        },

        setCategory: function (cat) {
            this.activeCategory = cat;
            this.categoriesContainer.find('.gs-cat-pill').removeClass('active');
            var $activePill = this.categoriesContainer.find('.gs-cat-pill[data-cat="' + cat + '"]');
            $activePill.addClass('active');

            // Aktif sekmeyi görünür alana merkezleyerek kaydır
            if ($activePill.length && this.categoriesContainer.length) {
                var pillEl = $activePill[0];
                var containerEl = this.categoriesContainer[0];
                var pillLeft = pillEl.offsetLeft;
                var pillWidth = pillEl.offsetWidth;
                var containerWidth = containerEl.offsetWidth;
                containerEl.scrollTo({
                    left: pillLeft - (containerWidth / 2) + (pillWidth / 2),
                    behavior: 'smooth'
                });
            }

            if (this.lastData) {
                this.renderResults(this.lastData);
            }
        },

        triggerSearch: function (query) {
            var self = this;
            this.currentQuery = query;
            this.spinner.show();
            this.clearBtn.hide();

            $.ajax({
                url: 'api/global_search.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    q: query,
                    category: 'all',
                    limit: 8
                },
                success: function (res) {
                    self.spinner.hide();
                    if (self.input.val().trim().length > 0) {
                        self.clearBtn.show();
                    }

                    if (res && res.status === 'success') {
                        self.lastData = res;
                        self.updateCounters(res.counts);
                        self.renderResults(res);
                        self.openDropdown();
                    }
                },
                error: function () {
                    self.spinner.hide();
                    if (self.input.val().trim().length > 0) {
                        self.clearBtn.show();
                    }
                }
            });
        },

        triggerMobileSearch: function (query) {
            var self = this;
            this.mobileSpinner.show();

            $.ajax({
                url: 'api/global_search.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    q: query,
                    category: 'all',
                    limit: 10
                },
                success: function (res) {
                    self.mobileSpinner.hide();
                    if (res && res.status === 'success') {
                        self.renderMobileResults(res, query);
                    }
                },
                error: function () {
                    self.mobileSpinner.hide();
                }
            });
        },

        updateCounters: function (counts) {
            if (!counts) return;
            $('#count-all').text(counts.all || 0);
            $('#count-personel').text(counts.personel || 0);
            $('#count-araclar').text(counts.araclar || 0);
            $('#count-demirbaslar').text(counts.demirbaslar || 0);
            $('#count-cariler').text(counts.cariler || 0);
            $('#count-evraklar').text(counts.evraklar || 0);
            $('#count-gorevler').text(counts.gorevler || 0);
            $('#count-kacak').text(counts.kacak || 0);
            $('#count-aparatlar').text(counts.aparatlar || 0);
            if (this.totalCountBadge) {
                this.totalCountBadge.text(counts.all || 0);
            }
        },

        highlightText: function (text, query) {
            if (!text) return '';
            if (!query) return $('<div>').text(text).html();

            var safeText = $('<div>').text(text).html();
            var escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var regex = new RegExp('(' + escapedQuery + ')', 'gi');
            return safeText.replace(regex, '<mark class="gs-highlight">$1</mark>');
        },

        renderResults: function (data) {
            var self = this;
            var html = '';
            var itemGlobalIndex = 0;
            var cat = this.activeCategory;
            var query = this.currentQuery;
            var results = data.results || {};

            var moduleConfig = {
                personel:    { title: 'PERSONELLER', icon: 'bx-user', color: 'blue', items: results.personel || [] },
                araclar:     { title: 'ARAÇLAR', icon: 'bx-car', color: 'amber', items: results.araclar || [] },
                demirbaslar: { title: 'DEMİRBAŞLAR', icon: 'bx-cube', color: 'purple', items: results.demirbaslar || [] },
                cariler:     { title: 'FİRMALAR & CARİLER', icon: 'bx-buildings', color: 'emerald', items: results.cariler || [] },
                evraklar:    { title: 'EVRAKLAR', icon: 'bx-file', color: 'indigo', items: results.evraklar || [] },
                gorevler:    { title: 'GÖREVLER', icon: 'bx-check-square', color: 'cyan', items: results.gorevler || [] },
                kacak:       { title: 'KAÇAK & SAHA TUTANAKLARI', icon: 'bx-shield-quarter', color: 'purple', items: results.kacak || [] },
                aparatlar:   { title: 'APARATLAR', icon: 'bx-wrench', color: 'amber', items: results.aparatlar || [] }
            };

            var modulesToRender = [];
            if (cat === 'all') {
                modulesToRender = ['personel', 'araclar', 'demirbaslar', 'cariler', 'evraklar', 'gorevler', 'kacak', 'aparatlar'];
            } else if (moduleConfig[cat]) {
                modulesToRender = [cat];
            }

            var totalCount = 0;
            modulesToRender.forEach(function (mKey) {
                var mod = moduleConfig[mKey];
                if (mod && mod.items && mod.items.length > 0) {
                    totalCount += mod.items.length;
                    html += '<div class="gs-category-group">';
                    html += '  <div class="gs-category-header">';
                    html += '    <span class="gs-cat-title"><i class="bx ' + mod.icon + '"></i> ' + mod.title + '</span>';
                    html += '    <span class="gs-cat-count-badge">' + mod.items.length + '</span>';
                    html += '  </div>';
                    html += '  <div class="gs-items-list">';

                    mod.items.forEach(function (item) {
                        var isFirst = (itemGlobalIndex === 0);
                        var activeClass = isFirst ? 'active' : '';

                        html += '<a href="' + item.url + '" class="gs-result-item ' + activeClass + '" data-index="' + itemGlobalIndex + '" data-type="' + item.type + '" data-id="' + item.id + '">';
                        
                        // Left Avatar / Badge
                        if (item.avatar_url) {
                            html += '  <div class="gs-item-avatar">';
                            html += '    <img src="' + item.avatar_url + '" class="rounded-circle w-100 h-100 object-fit-cover" alt="' + $('<div>').text(item.title).html() + '">';
                            html += '    <span class="gs-avatar-dot ' + (item.badge_class === 'badge-success' ? 'bg-success' : 'bg-danger') + '"></span>';
                            html += '  </div>';
                        } else {
                            html += '  <div class="gs-item-avatar gs-avatar-' + (item.color_theme || 'blue') + '">';
                            html += '    <span>' + (item.initial || '•') + '</span>';
                            html += '    <span class="gs-avatar-dot ' + (item.badge_class === 'badge-success' ? 'bg-success' : 'bg-danger') + '"></span>';
                            html += '  </div>';
                        }

                        // Middle Content
                        html += '  <div class="gs-item-content">';
                        html += '    <div class="gs-item-row-primary">';
                        html += '      <span class="gs-item-title">' + self.highlightText(item.title, query) + '</span>';
                        if (item.extra_info) {
                            html += '      <span class="gs-item-extra">' + self.highlightText(item.extra_info, query) + '</span>';
                        }
                        if (item.date) {
                            html += '      <span class="gs-item-date"><i class="bx bx-calendar-event"></i> ' + item.date + '</span>';
                        }
                        html += '    </div>';

                        html += '    <div class="gs-item-row-secondary">';
                        html += '      <span class="gs-item-subtitle">' + self.highlightText(item.subtitle, query) + '</span>';
                        html += '    </div>';
                        html += '  </div>';

                        // Right Status & Chevron
                        html += '  <div class="gs-item-actions">';
                        if (item.badge) {
                            html += '    <span class="gs-status-pill ' + item.badge_class + '">' + item.badge + '</span>';
                        }
                        html += '    <i class="bx bx-chevron-right gs-item-arrow"></i>';
                        html += '  </div>';

                        html += '</a>';
                        itemGlobalIndex++;
                    });

                    html += '  </div>';
                    html += '</div>';
                }
            });

            this.totalVisibleItems = itemGlobalIndex;
            this.selectedIndex = itemGlobalIndex > 0 ? 0 : -1;

            if (totalCount === 0) {
                html = '<div class="gs-empty-state">';
                html += '  <div class="gs-empty-icon"><i class="bx bx-search-alt"></i></div>';
                html += '  <div class="gs-empty-title">"' + $('<div>').text(query).html() + '" ile eşleşen kayıt bulunamadı</div>';
                html += '  <div class="gs-empty-subtitle">Farklı bir anahtar kelime, numara, plaka veya isim deneyebilirsiniz.</div>';
                html += '</div>';
            }

            this.resultsContainer.html(html);
            if (this.totalCountBadge) {
                this.totalCountBadge.text(data.counts ? (cat === 'all' ? data.counts.all : (data.counts[cat] || 0)) : totalCount);
            }
        },

        renderInitialSuggestions: function () {
            var allowed = window.GLOBAL_SEARCH_ALLOWED_MODULES || ['personel', 'araclar', 'demirbaslar', 'cariler', 'evraklar', 'gorevler', 'kacak', 'aparatlar'];
            var allCards = [
                { key: 'personel',    url: 'index.php?p=personel/list',       icon: 'bx-user text-primary',         label: 'Personeller' },
                { key: 'araclar',     url: 'index.php?p=arac-takip/list',     icon: 'bx-car text-warning',          label: 'Araç Takip' },
                { key: 'demirbaslar', url: 'index.php?p=demirbas/list',       icon: 'bx-cube text-purple',          label: 'Demirbaşlar' },
                { key: 'cariler',     url: 'index.php?p=cari/list',           icon: 'bx-buildings text-success',    label: 'Cari Hesaplar' },
                { key: 'evraklar',    url: 'index.php?p=evrak-takip/list',    icon: 'bx-file text-indigo',          label: 'Evrak Takip' },
                { key: 'kacak',       url: 'index.php?p=kacak/list',          icon: 'bx-shield-quarter text-danger',label: 'Kaçak Kontrol' },
                { key: 'gorevler',    url: 'index.php?p=gorevler/list',       icon: 'bx-check-square text-teal',    label: 'Görevler' },
                { key: 'aparatlar',   url: 'index.php?p=aparat-takip/list',   icon: 'bx-wrench text-secondary',     label: 'Aparat Takip' }
            ];

            var filteredCards = allCards.filter(function (card) {
                return allowed.indexOf(card.key) !== -1;
            });

            var html = '<div class="gs-suggestions-wrap">';
            html += '  <div class="gs-suggestions-header">Hızlı Modül Sayfaları</div>';
            html += '  <div class="gs-suggestions-grid">';
            filteredCards.forEach(function (c) {
                html += '    <a href="' + c.url + '" class="gs-suggestion-card"><i class="bx ' + c.icon + '"></i><span>' + c.label + '</span></a>';
            });
            html += '  </div>';
            html += '</div>';

            this.resultsContainer.html(html);
            this.updateCounters({ all: 0, personel: 0, araclar: 0, demirbaslar: 0, cariler: 0, evraklar: 0, gorevler: 0, kacak: 0, aparatlar: 0 });
            this.totalVisibleItems = 0;
            this.selectedIndex = -1;
            this.openDropdown();
        },

        renderMobileInitial: function () {
            var html = `
                <div class="p-3 text-center text-muted font-size-12">
                    <i class="bx bx-search-alt font-size-24 d-block mb-1 text-muted opacity-50"></i>
                    Personel, araç, demirbaş, cari veya evrak aramak için yazmaya başlayın.
                </div>
            `;
            this.mobileResultsContainer.html(html);
        },

        renderMobileResults: function (data, query) {
            var self = this;
            var html = '';
            var results = data.results || {};
            var total = data.counts ? data.counts.all : 0;

            if (total === 0) {
                this.mobileResultsContainer.html(`
                    <div class="p-4 text-center text-muted font-size-13">
                        <i class="bx bx-search-alt font-size-24 d-block mb-1 opacity-50"></i>
                        "${$('<div>').text(query).html()}" ile eşleşen kayıt bulunamadı.
                    </div>
                `);
                return;
            }

            var moduleConfig = {
                personel:    { title: 'Personeller', icon: 'bx-user', items: results.personel || [] },
                araclar:     { title: 'Araçlar', icon: 'bx-car', items: results.araclar || [] },
                demirbaslar: { title: 'Demirbaşlar', icon: 'bx-cube', items: results.demirbaslar || [] },
                cariler:     { title: 'Cariler', icon: 'bx-buildings', items: results.cariler || [] },
                evraklar:    { title: 'Evraklar', icon: 'bx-file', items: results.evraklar || [] },
                gorevler:    { title: 'Görevler', icon: 'bx-check-square', items: results.gorevler || [] },
                kacak:       { title: 'Kaçak Kontrol', icon: 'bx-shield-quarter', items: results.kacak || [] },
                aparatlar:   { title: 'Aparatlar', icon: 'bx-wrench', items: results.aparatlar || [] }
            };

            Object.keys(moduleConfig).forEach(function (key) {
                var mod = moduleConfig[key];
                if (mod.items && mod.items.length > 0) {
                    html += '<div class="gs-mobile-group p-2 border-bottom">';
                    html += '  <div class="font-size-11 fw-bold text-muted text-uppercase mb-1"><i class="bx ' + mod.icon + ' me-1"></i>' + mod.title + ' (' + mod.items.length + ')</div>';
                    
                    mod.items.forEach(function (item) {
                        html += '<a href="' + item.url + '" class="d-flex align-items-center p-2 rounded text-dark text-decoration-none gs-mobile-item">';
                        html += '  <div class="flex-grow-1 min-w-0">';
                        html += '    <div class="fw-semibold font-size-13 text-truncate">' + self.highlightText(item.title, query) + '</div>';
                        html += '    <div class="text-muted font-size-11 text-truncate">' + self.highlightText(item.subtitle, query) + '</div>';
                        html += '  </div>';
                        if (item.badge) {
                            html += '  <span class="badge ' + (item.badge_class === 'badge-success' ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary') + ' font-size-10 ms-2 flex-shrink-0">' + item.badge + '</span>';
                        }
                        html += '</a>';
                    });

                    html += '</div>';
                }
            });

            this.mobileResultsContainer.html(html);
        },

        moveSelection: function (step) {
            if (this.totalVisibleItems <= 0) return;

            var newIndex = this.selectedIndex + step;
            if (newIndex < 0) {
                newIndex = this.totalVisibleItems - 1;
            } else if (newIndex >= this.totalVisibleItems) {
                newIndex = 0;
            }

            this.setSelectedIndex(newIndex);
        },

        setSelectedIndex: function (index) {
            this.selectedIndex = index;
            var items = this.resultsContainer.find('.gs-result-item');
            items.removeClass('active');

            var target = items.filter('[data-index="' + index + '"]');
            if (target.length) {
                target.addClass('active');
                
                // Auto scroll into view
                var container = this.resultsContainer;
                var targetTop = target.position().top;
                var targetBottom = targetTop + target.outerHeight();
                var containerHeight = container.height();

                if (targetBottom > containerHeight) {
                    container.scrollTop(container.scrollTop() + (targetBottom - containerHeight) + 10);
                } else if (targetTop < 0) {
                    container.scrollTop(container.scrollTop() + targetTop - 10);
                }
            }
        },

        activateSelected: function () {
            var target = null;
            if (this.selectedIndex >= 0) {
                target = this.resultsContainer.find('.gs-result-item[data-index="' + this.selectedIndex + '"]');
            } else {
                target = this.resultsContainer.find('.gs-result-item:first');
            }

            if (target && target.length && target.attr('href')) {
                window.location.href = target.attr('href');
                return;
            }

            // Kategori seçiliyse ilgili modülün arama filtreli sayfasına yönlendir
            if (this.currentQuery && this.currentQuery.trim().length > 0) {
                var catMap = {
                    personel: 'index.php?p=personel/list&search=',
                    araclar: 'index.php?p=arac-takip/list&search=',
                    demirbaslar: 'index.php?p=demirbas/list&search=',
                    cariler: 'index.php?p=cari/list&search=',
                    evraklar: 'index.php?p=evrak-takip/list&search=',
                    gorevler: 'index.php?p=gorevler/list&search=',
                    kacak: 'index.php?p=kacak/list&search=',
                    aparatlar: 'index.php?p=aparat-takip/list&tab=pane-tanimlar&search='
                };
                if (this.activeCategory !== 'all' && catMap[this.activeCategory]) {
                    window.location.href = catMap[this.activeCategory] + encodeURIComponent(this.currentQuery.trim());
                }
            }
        }
    };

    $(document).ready(function () {
        GlobalSearch.init();
    });

})(jQuery);
