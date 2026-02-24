(function () {
  'use strict';

  function toInt(value, fallback) {
    var n = parseInt(value, 10);
    return Number.isFinite(n) ? n : fallback;
  }

  function toFloat(value, fallback) {
    var n = parseFloat(value);
    return Number.isFinite(n) ? n : fallback;
  }

  function toBool(value, fallback) {
    if (value === undefined || value === null || value === '') {
      return fallback;
    }

    var normalized = String(value).trim().toLowerCase();
    if (['1', 'true', 'yes', 'on', 'si'].indexOf(normalized) >= 0) {
      return true;
    }
    if (['0', 'false', 'no', 'off'].indexOf(normalized) >= 0) {
      return false;
    }

    return fallback;
  }

  function getHashPage() {
    var m = window.location.hash.match(/(?:^#|[&#])p=(\d+)/i);
    if (!m) {
      return null;
    }

    var p = toInt(m[1], NaN);
    return Number.isFinite(p) && p > 0 ? p : null;
  }

  function setHashPage(pageNumber) {
    var safePage = Math.max(1, toInt(pageNumber, 1));
    var nextHash = '#p=' + safePage;

    if (history && typeof history.replaceState === 'function') {
      var base = window.location.href.split('#')[0];
      history.replaceState(null, '', base + nextHash);
    } else {
      window.location.hash = 'p=' + safePage;
    }
  }

  function createEl(tag, className, text) {
    var el = document.createElement(tag);
    if (className) {
      el.className = className;
    }
    if (text !== undefined && text !== null) {
      el.textContent = text;
    }
    return el;
  }

  function setError(root, message) {
    root.innerHTML = '';
    root.classList.remove('is-loading');
    var err = createEl('div', 'gacetaflip-error', message);
    root.appendChild(err);
  }

  function setStatus(statusEl, message) {
    if (!statusEl) {
      return;
    }
    statusEl.textContent = message;
  }

  function applyZoom(stageEl, zoomLabelEl, zoomValue) {
    var safeZoom = Math.max(0.6, Math.min(2.2, zoomValue));
    stageEl.style.setProperty('--gacetaflip-zoom', String(safeZoom));
    if (zoomLabelEl) {
      zoomLabelEl.textContent = Math.round(safeZoom * 100) + '%';
    }
    return safeZoom;
  }

  async function renderNative(root) {
    var pdfUrl = root.dataset.file || '';
    var width = toInt(root.dataset.width, 560);
    var height = toInt(root.dataset.height, 760);
    var startPage = Math.max(1, toInt(root.dataset.start, 1));
    var maxPages = Math.max(0, toInt(root.dataset.maxpages, 0));
    var renderScale = toFloat(root.dataset.scale, 1.35);
    var zoomStep = Math.max(0.05, Math.min(0.6, toFloat(root.dataset.zoomstep, 0.15)));
    var showDownload = toBool(root.dataset.download, true);
    var updateHash = toBool(root.dataset.updatehash, true);

    if (!pdfUrl) {
      setError(root, 'OpenLeaf Gazette: missing file parameter.');
      return;
    }

    if (!window.pdfjsLib) {
      setError(root, 'OpenLeaf Gazette: unable to load pdf.js.');
      return;
    }

    if (!window.St || !window.St.PageFlip) {
      setError(root, 'OpenLeaf Gazette: unable to load StPageFlip.');
      return;
    }

    window.pdfjsLib.GlobalWorkerOptions.workerSrc = window.GacetaFlipWorkerSrc || '';

    root.classList.add('is-loading');
    root.innerHTML = '';

    var card = createEl('div', 'gacetaflip-card');
    var toolbar = createEl('div', 'gacetaflip-toolbar');
    var stageWrap = createEl('div', 'gacetaflip-stage-wrap');
    var stage = createEl('div', 'gacetaflip-stage');
    var status = createEl('div', 'gacetaflip-status', 'Cargando PDF...');
    var hiddenPages = createEl('div', 'gacetaflip-pages');

    stageWrap.appendChild(stage);
    card.appendChild(toolbar);
    card.appendChild(stageWrap);
    card.appendChild(status);
    card.appendChild(hiddenPages);
    root.appendChild(card);

    var leftTools = createEl('div', 'gacetaflip-tools');
    var rightTools = createEl('div', 'gacetaflip-tools');

    var prevBtn = createEl('button', 'gacetaflip-btn', 'Anterior');
    var nextBtn = createEl('button', 'gacetaflip-btn', 'Siguiente');
    var counter = createEl('span', 'gacetaflip-counter', '0 / 0');

    var gotoLabel = createEl('label', 'gacetaflip-pagejump');
    gotoLabel.innerHTML = 'Ir a:';
    var gotoInput = createEl('input', 'gacetaflip-pageinput');
    gotoInput.type = 'number';
    gotoInput.min = '1';
    gotoInput.step = '1';
    var gotoBtn = createEl('button', 'gacetaflip-btn', 'OK');
    gotoLabel.appendChild(gotoInput);

    var zoomOutBtn = createEl('button', 'gacetaflip-btn', '-');
    var zoomInBtn = createEl('button', 'gacetaflip-btn', '+');
    var zoomResetBtn = createEl('button', 'gacetaflip-btn', '100%');
    var zoomLabel = createEl('span', 'gacetaflip-zoomlabel', '100%');

    var fullBtn = createEl('button', 'gacetaflip-btn', 'Pantalla completa');

    leftTools.appendChild(prevBtn);
    leftTools.appendChild(counter);
    leftTools.appendChild(nextBtn);
    leftTools.appendChild(gotoLabel);
    leftTools.appendChild(gotoBtn);

    rightTools.appendChild(zoomOutBtn);
    rightTools.appendChild(zoomLabel);
    rightTools.appendChild(zoomInBtn);
    rightTools.appendChild(zoomResetBtn);
    rightTools.appendChild(fullBtn);

    if (showDownload) {
      var downloadLink = createEl('a', 'gacetaflip-btn gacetaflip-btn-link', 'Descargar PDF');
      downloadLink.href = pdfUrl;
      downloadLink.target = '_blank';
      downloadLink.rel = 'noopener noreferrer';
      rightTools.appendChild(downloadLink);
    }

    toolbar.appendChild(leftTools);
    toolbar.appendChild(rightTools);

    var currentZoom = 1;
    currentZoom = applyZoom(stage, zoomLabel, currentZoom);

    try {
      var loadingTask = window.pdfjsLib.getDocument({
        url: pdfUrl,
        cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/cmaps/',
        cMapPacked: true
      });

      var pdf = await loadingTask.promise;
      var totalPages = pdf.numPages;
      var renderCount = maxPages > 0 ? Math.min(totalPages, maxPages) : totalPages;

      setStatus(status, 'Renderizando ' + renderCount + ' paginas...');

      var i;
      for (i = 1; i <= renderCount; i += 1) {
        setStatus(status, 'Renderizando pagina ' + i + ' de ' + renderCount + '...');
        var page = await pdf.getPage(i);
        var viewport = page.getViewport({ scale: renderScale });

        var canvas = document.createElement('canvas');
        canvas.width = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);

        var ctx = canvas.getContext('2d', { alpha: false });
        await page.render({
          canvasContext: ctx,
          viewport: viewport
        }).promise;

        var pageEl = createEl('div', 'gacetaflip-page');
        if (i === 1 || i === renderCount) {
          pageEl.dataset.density = 'hard';
        }

        var image = createEl('img', 'gacetaflip-image');
        image.src = canvas.toDataURL('image/jpeg', 0.92);
        image.alt = 'Pagina ' + i;

        var number = createEl('div', 'gacetaflip-pagenum', String(i));

        pageEl.appendChild(image);
        pageEl.appendChild(number);
        hiddenPages.appendChild(pageEl);
      }

      if (!hiddenPages.children.length) {
        setError(root, 'GacetaFlip: no hay paginas para mostrar.');
        return;
      }

      var initialFromHash = updateHash ? getHashPage() : null;
      var initialPage = initialFromHash || startPage;
      initialPage = Math.max(1, Math.min(renderCount, initialPage));

      var pageFlip = new window.St.PageFlip(stage, {
        width: width,
        height: height,
        size: 'stretch',
        minWidth: Math.max(280, Math.round(width * 0.55)),
        maxWidth: Math.max(width, Math.round(width * 2.4)),
        minHeight: Math.max(320, Math.round(height * 0.55)),
        maxHeight: Math.max(height, Math.round(height * 2.4)),
        maxShadowOpacity: 0.38,
        showCover: true,
        usePortrait: true,
        mobileScrollSupport: false,
        flippingTime: 900,
        startPage: initialPage - 1
      });

      var pages = Array.prototype.slice.call(hiddenPages.children);
      if (typeof pageFlip.loadFromHTML === 'function') {
        pageFlip.loadFromHTML(pages);
      } else {
        pageFlip.loadFromHtml(pages);
      }

      function syncUi(pageIdx) {
        var safeIdx = Math.max(0, Math.min(renderCount - 1, pageIdx));
        var pageNum = safeIdx + 1;
        counter.textContent = pageNum + ' / ' + renderCount;
        gotoInput.value = String(pageNum);
        prevBtn.disabled = pageNum <= 1;
        nextBtn.disabled = pageNum >= renderCount;

        if (updateHash) {
          setHashPage(pageNum);
        }
      }

      syncUi(pageFlip.getCurrentPageIndex());

      pageFlip.on('flip', function (e) {
        syncUi(e.data);
      });

      prevBtn.addEventListener('click', function () {
        pageFlip.flipPrev('bottom');
      });

      nextBtn.addEventListener('click', function () {
        pageFlip.flipNext('bottom');
      });

      gotoBtn.addEventListener('click', function () {
        var target = Math.max(1, Math.min(renderCount, toInt(gotoInput.value, 1)));
        pageFlip.flip(target - 1, 'top');
      });

      gotoInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          gotoBtn.click();
        }
      });

      zoomOutBtn.addEventListener('click', function () {
        currentZoom = applyZoom(stage, zoomLabel, currentZoom - zoomStep);
      });

      zoomInBtn.addEventListener('click', function () {
        currentZoom = applyZoom(stage, zoomLabel, currentZoom + zoomStep);
      });

      zoomResetBtn.addEventListener('click', function () {
        currentZoom = applyZoom(stage, zoomLabel, 1);
      });

      fullBtn.addEventListener('click', function () {
        var target = card;
        if (!document.fullscreenElement) {
          if (target.requestFullscreen) {
            target.requestFullscreen();
          }
        } else if (document.exitFullscreen) {
          document.exitFullscreen();
        }
      });

      var statusText = renderCount < totalPages
        ? ('Mostrando ' + renderCount + ' de ' + totalPages + ' paginas.')
        : ('Total de paginas: ' + totalPages + '.');

      setStatus(status, statusText);
      root.classList.remove('is-loading');
    } catch (error) {
      console.error('OpenLeaf Gazette error:', error);
      setError(root, 'OpenLeaf Gazette: unable to load the PDF. Check file path and permissions.');
    }
  }

  function init() {
    var nodes = document.querySelectorAll('[data-gacetaflip="1"]');
    nodes.forEach(function (node) {
      if (node.dataset.gacetaflipInit === '1') {
        return;
      }

      node.dataset.gacetaflipInit = '1';
      renderNative(node);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
