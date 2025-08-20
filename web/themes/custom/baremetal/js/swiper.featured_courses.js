// js/swiper.featured_courses.js
(function (Drupal, once) {
  Drupal.behaviors.featuredCoursesThumbs = {
    attach(context) {
      // Run once per wrapper (your outermost paragraph has this class).
      once('featuredCoursesPair', '.js-featured-course-pair', context).forEach((wrap) => {
        // Robust finders – match your exact IDs or fall back to class-based.
        const find = (root, sels) => {
          for (const s of sels) {
            const el = root.querySelector(s);
            if (el) return el;
          }
          return null;
        };

        const mainEl = find(wrap, [
          '#swiper-featured-featured-courses-main',
          '.js-featured-courses-main .swiper',
          '.js-featured-courses-main .swiper-container'
        ]);
        const thumbsEl = find(wrap, [
          '#swiper-featured-featured-courses-thumbs',
          '.js-featured-courses-thumbs .swiper',
          '.js-featured-courses-thumbs .swiper-container'
        ]);
        if (!mainEl || !thumbsEl) return;

        console.log('Featured courses thumbs initialized', {
          mainEl,
          thumbsEl,
          mainSwiper: mainEl.swiper,
          thumbsSwiper: thumbsEl.swiper
        });

        // Wait until Swiper Formatter has initialized and set el.swiper.
        const waitForSwiper = (el, attempts = 40) =>
          new Promise((resolve) => {
            const tick = () => {
              if (el.swiper) return resolve(el.swiper);
              if (attempts-- <= 0) return resolve(null);
              setTimeout(tick, 50);
            };
            tick();
          });

        Promise.all([waitForSwiper(mainEl), waitForSwiper(thumbsEl)]).then(([main, thumbs]) => {
          // If the module hasn’t initialized for some reason, initialize ourselves.
          // eslint-disable-next-line no-undef
          if (!thumbs) thumbs = new Swiper(thumbsEl, {
            slidesPerView: 'auto',
            spaceBetween: 12,
            freeMode: true,
            watchSlidesProgress: true,
            slideToClickedSlide: true,
            a11y: { enabled: true },
            keyboard: { enabled: true },
            watchOverflow: true,
            breakpoints: { 480:{slidesPerView:3}, 768:{slidesPerView:4}, 1024:{slidesPerView:6} }
          });

          // eslint-disable-next-line no-undef
          if (!main) main = new Swiper(mainEl, {
            slidesPerView: 1,
            a11y: { enabled: true },
            keyboard: { enabled: true },
            watchOverflow: true
          });

          // Try native thumbs linking first.
          // Some builds don’t attach thumbs until provided; this handles both cases.
          try {
            if (!main.thumbs) main.thumbs = {};
            main.thumbs.swiper = thumbs;
            if (typeof main.thumbs.init === 'function') {
              main.thumbs.init();
              if (typeof main.thumbs.update === 'function') main.thumbs.update(true);
            } else {
              // Thumbs plugin wasn’t active on first init — re-init main with thumbs preserved.
              const params = Object.assign({}, main.params);
              main.destroy(true, false);
              params.thumbs = { swiper: thumbs };
              // eslint-disable-next-line no-undef
              main = new Swiper(mainEl, params);
            }
          } catch (e) {
            // Absolute fallback: re-init with thumbs.
            const params = Object.assign({}, main.params || {});
            try { main.destroy(true, false); } catch (_) {}
            params.thumbs = { swiper: thumbs };
            // eslint-disable-next-line no-undef
            new Swiper(mainEl, params);
          }

          // Optional polish: if there’s only one slide, disable nav & dragging.
          const count = (el) => el.querySelectorAll('.swiper-slide').length;
          if (count(mainEl) < 2) {
            main.allowTouchMove = false;
            wrap.querySelectorAll('.swiper-button-prev, .swiper-button-next, .swiper-pagination')
              .forEach(el => el && (el.style.display = 'none'));
          }
        });
      });
    }
  };
})(Drupal, once);
