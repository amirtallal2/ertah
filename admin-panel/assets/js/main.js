/**
 * ملف JavaScript الرئيسي
 * Main JavaScript File
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // تبديل الشريط الجانبي على الشاشات الصغيرة
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // إغلاق الشريط الجانبي عند النقر خارجه
    document.addEventListener('click', function(e) {
        if (sidebar && sidebar.classList.contains('active')) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
    
    // تأكيد الحذف
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'هل أنت متأكد من هذا الإجراء؟';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // إغلاق التنبيهات تلقائياً
    document.querySelectorAll('.alert').forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function() {
                alert.remove();
            }, 300);
        }, 5000);
    });
    
    // معاينة الصور قبل الرفع
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function(input) {
        input.addEventListener('change', function() {
            const previewId = this.getAttribute('data-preview');
            const preview = document.getElementById(previewId);
            
            if (preview && this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
    
    // البحث الفوري
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                const form = searchInput.closest('form');
                if (form) form.submit();
            }, 500);
        });
    }
    
    // تفعيل التولتيب
    document.querySelectorAll('[data-tooltip]').forEach(function(element) {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
            tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
        });
        
        element.addEventListener('mouseleave', function() {
            document.querySelectorAll('.tooltip').forEach(t => t.remove());
        });
    });
    
    // تحديد الكل في الجداول
    const selectAll = document.getElementById('select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.select-item').forEach(function(checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
    }
    
    // تنسيق أرقام الهاتف
    document.querySelectorAll('input[type="tel"]').forEach(function(input) {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+]/g, '');
        });
    });
    
    // تأثير الزر عند النقر
    document.querySelectorAll('.btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            this.appendChild(ripple);
            
            const rect = this.getBoundingClientRect();
            ripple.style.left = (e.clientX - rect.left) + 'px';
            ripple.style.top = (e.clientY - rect.top) + 'px';
            
            setTimeout(function() {
                ripple.remove();
            }, 600);
        });
    });

    // استبدال رمز العملة بصورة PNG
    replaceCurrencySymbols(document.body);
    observeCurrencySymbolChanges();
    
});

/**
 * عرض المودال
 */
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * إخفاء المودال
 */
function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

/**
 * إغلاق المودال عند النقر على الخلفية
 */
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

/**
 * تنسيق الأرقام
 */
function formatNumber(num) {
    return new Intl.NumberFormat('ar-SA').format(num);
}

/**
 * تنسيق المبالغ
 */
function formatMoney(amount, currency = '⃁') {
    return formatNumber(amount) + ' ' + currency;
}

function resolveSarSymbolImageSrc() {
    const script = document.querySelector('script[src*="assets/js/main.js"]');
    if (script && script.src) {
        return script.src.replace(/assets\/js\/main\.js(?:\?.*)?$/, 'assets/images/currency/saudi_riyal_symbol.png');
    }
    return 'assets/images/currency/saudi_riyal_symbol.png';
}

const sarSymbolImageSrc = resolveSarSymbolImageSrc();

function isSkippableCurrencyNode(node) {
    const parent = node && node.parentElement;
    if (!parent) return true;
    const tag = parent.tagName;
    if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'INPUT', 'OPTION'].includes(tag)) {
        return true;
    }
    if (parent.closest('.sar-symbol-img')) {
        return true;
    }
    return false;
}

function createSarSymbolElement() {
    const image = document.createElement('img');
    image.className = 'sar-symbol-img';
    image.src = sarSymbolImageSrc;
    image.alt = 'رمز الريال السعودي';
    image.setAttribute('aria-label', 'رمز الريال السعودي');
    image.setAttribute('title', 'رمز الريال السعودي');
    image.loading = 'lazy';
    image.decoding = 'async';
    return image;
}

function replaceCurrencyInTextNode(textNode) {
    if (!textNode || !textNode.nodeValue) return false;
    if (isSkippableCurrencyNode(textNode)) return false;

    const value = textNode.nodeValue;
    const pattern = /(⃁|ر\.س|SAR)/g;
    if (!pattern.test(value)) return false;

    const parts = value.split(pattern);
    const fragment = document.createDocumentFragment();
    parts.forEach(function(part) {
        if (part === '⃁' || part === 'ر.س' || part === 'SAR') {
            fragment.appendChild(createSarSymbolElement());
        } else if (part) {
            fragment.appendChild(document.createTextNode(part));
        }
    });

    textNode.parentNode.replaceChild(fragment, textNode);
    return true;
}

function replaceCurrencySymbols(root) {
    if (!root) return;
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const textNodes = [];
    let current = walker.nextNode();
    while (current) {
        textNodes.push(current);
        current = walker.nextNode();
    }
    textNodes.forEach(replaceCurrencyInTextNode);
}

let currencyObserverStarted = false;
function observeCurrencySymbolChanges() {
    if (currencyObserverStarted || !document.body) return;
    currencyObserverStarted = true;

    const observer = new MutationObserver(function(mutations) {
        for (const mutation of mutations) {
            if (mutation.type === 'characterData' && mutation.target.nodeType === Node.TEXT_NODE) {
                replaceCurrencyInTextNode(mutation.target);
                continue;
            }
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === Node.TEXT_NODE) {
                    replaceCurrencyInTextNode(node);
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    replaceCurrencySymbols(node);
                }
            });
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true
    });
}

/**
 * نسخ النص
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('تم النسخ بنجاح', 'success');
    });
}

/**
 * عرض رسالة منبثقة
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} animate-slideUp`;
    toast.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 300px;';
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-circle' : 'info-circle')}"></i><span>${message}</span>`;
    
    document.body.appendChild(toast);
    
    setTimeout(function() {
        toast.style.opacity = '0';
        setTimeout(function() {
            toast.remove();
        }, 300);
    }, 3000);
}

/**
 * طلب AJAX
 */
async function fetchData(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            ...options
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        return await response.json();
    } catch (error) {
        console.error('Fetch error:', error);
        showToast('حدث خطأ في الاتصال', 'danger');
        throw error;
    }
}
