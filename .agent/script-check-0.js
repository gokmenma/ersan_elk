
                window.setTimeout(function () {
                    try {
                        var skeleton = document.getElementById('dashboard-page-skeleton');
                        var content = document.getElementById('dashboard-page-content');
                        var criticalStyle = document.getElementById('dashboard-skeleton-critical');

                        if (content && window.getComputedStyle(content).display === 'none') {
                            content.style.display = '';
                        }

                        if (skeleton && skeleton.style.display !== 'none') {
                            skeleton.style.display = 'none';
                        }

                        if (criticalStyle) {
                            criticalStyle.remove();
                        }
                    } catch (e) {}
                }, 1800);
            
