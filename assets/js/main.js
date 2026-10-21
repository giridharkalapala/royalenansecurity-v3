/**
 * ROYAL ENAN SECURITY SERVICES (RESS)
 * Interactive UI Engine & Controller
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Initialize AOS (Animate On Scroll)
  if (typeof AOS !== 'undefined') {
    AOS.init({
      duration: 800,
      easing: 'ease-out-cubic',
      once: true,
      offset: 50
    });
  }

  // 2. Scroll-driven Header Appearance & Active Nav Spy
  const header = document.getElementById('site-header');
  const desktopNavLinks = document.querySelectorAll('.desktop-nav-link');
  const sections = document.querySelectorAll('section[id], header[id]');

  const handleScroll = () => {
    if (header) {
      if (window.scrollY > 30) {
        header.classList.add('is-scrolled');
      } else {
        header.classList.remove('is-scrolled');
      }
    }

    // Active Section Spy for Desktop Nav
    const scrollPosition = window.scrollY + 140;
    sections.forEach(section => {
      const top = section.offsetTop;
      const height = section.offsetHeight;
      const id = section.getAttribute('id');
      if (id && scrollPosition >= top && scrollPosition < top + height) {
        desktopNavLinks.forEach(link => {
          link.classList.remove('active');
          if (link.getAttribute('href') === `#${id}`) {
            link.classList.add('active');
          }
        });
      }
    });
  };

  window.addEventListener('scroll', handleScroll, { passive: true });
  handleScroll(); // Initial run

  // 3. Mobile Navigation Drawer & Toggle Menu
  const menuToggleBtn = document.getElementById('menu-toggle-btn');
  const mobileDrawer = document.getElementById('mobile-nav-drawer');
  const drawerCloseBtn = document.getElementById('drawer-close-btn');
  const drawerLinks = document.querySelectorAll('.drawer-link');

  const openDrawer = () => {
    if (!mobileDrawer || !menuToggleBtn) return;
    mobileDrawer.classList.add('is-active');
    mobileDrawer.setAttribute('aria-hidden', 'false');
    menuToggleBtn.classList.add('is-active');
    menuToggleBtn.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  };

  const closeDrawer = () => {
    if (!mobileDrawer || !menuToggleBtn) return;
    mobileDrawer.classList.remove('is-active');
    mobileDrawer.setAttribute('aria-hidden', 'true');
    menuToggleBtn.classList.remove('is-active');
    menuToggleBtn.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  };

  if (menuToggleBtn) {
    menuToggleBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isActive = mobileDrawer && mobileDrawer.classList.contains('is-active');
      if (isActive) {
        closeDrawer();
      } else {
        openDrawer();
      }
    });
  }

  if (drawerCloseBtn) {
    drawerCloseBtn.addEventListener('click', closeDrawer);
  }

  drawerLinks.forEach(link => {
    link.addEventListener('click', closeDrawer);
  });

  // Close drawer on backdrop click
  document.addEventListener('click', (e) => {
    if (mobileDrawer && mobileDrawer.classList.contains('is-active')) {
      const container = mobileDrawer.querySelector('.cred-drawer-container');
      if (container && !container.contains(e.target) && e.target !== menuToggleBtn && !menuToggleBtn.contains(e.target)) {
        closeDrawer();
      }
    }
  });

  // 4. ISO 9001:2015 Certificate Lightbox Modal
  const certModal = document.getElementById('cert-lightbox-modal');
  const certModalCloseBtn = document.getElementById('cert-modal-close-btn');
  const certModalDismissBtn = document.getElementById('cert-modal-dismiss-btn');
  const certOpenPreview = document.getElementById('open-cert-preview');
  const certModalTriggerBtn = document.getElementById('btn-cert-modal-trigger');
  const certCtaBtn = document.getElementById('view-cert-cta-btn');

  const openCertModal = () => {
    if (!certModal) return;
    certModal.classList.add('is-active');
    certModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  };

  const closeCertModal = () => {
    if (!certModal) return;
    certModal.classList.remove('is-active');
    certModal.setAttribute('aria-hidden', 'true');
    // Only restore scroll if drawer is not active
    if (!mobileDrawer || !mobileDrawer.classList.contains('is-active')) {
      document.body.style.overflow = '';
    }
  };

  if (certOpenPreview) certOpenPreview.addEventListener('click', openCertModal);
  if (certModalTriggerBtn) certModalTriggerBtn.addEventListener('click', openCertModal);
  if (certCtaBtn) certCtaBtn.addEventListener('click', openCertModal);

  if (certModalCloseBtn) certModalCloseBtn.addEventListener('click', closeCertModal);
  if (certModalDismissBtn) certModalDismissBtn.addEventListener('click', closeCertModal);

  // Close cert modal on backdrop click
  if (certModal) {
    certModal.addEventListener('click', (e) => {
      const modalBox = certModal.querySelector('.cert-modal-box');
      if (modalBox && !modalBox.contains(e.target)) {
        closeCertModal();
      }
    });
  }

  // Global ESC Key Handler
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (certModal && certModal.classList.contains('is-active')) {
        closeCertModal();
      } else if (mobileDrawer && mobileDrawer.classList.contains('is-active')) {
        closeDrawer();
      }
    }
  });

  // 5. Testimonials Synchronized Carousel
  const testiSlides = document.querySelectorAll('.testi-slide');
  const testiDots = document.querySelectorAll('.testi-dot');
  const miniCards = document.querySelectorAll('.testi-mini-card');
  const prevBtn = document.getElementById('testi-prev-btn');
  const nextBtn = document.getElementById('testi-next-btn');
  const featuredBox = document.getElementById('featured-testi-box');
  let currentSlide = 0;
  let autoplayTimer = null;

  const showSlide = (index) => {
    if (testiSlides.length === 0) return;
    const total = testiSlides.length;
    currentSlide = (index + total) % total;

    testiSlides.forEach((slide, i) => {
      slide.classList.toggle('is-active', i === currentSlide);
    });

    testiDots.forEach((dot, i) => {
      const isActive = i === currentSlide;
      dot.classList.toggle('is-active', isActive);
      dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    miniCards.forEach((card, i) => {
      card.classList.toggle('is-active', i === currentSlide);
    });
  };

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      showSlide(currentSlide - 1);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      showSlide(currentSlide + 1);
    });
  }

  testiDots.forEach(dot => {
    dot.addEventListener('click', () => {
      const idx = parseInt(dot.dataset.index, 10);
      showSlide(idx);
    });
  });

  miniCards.forEach(card => {
    card.addEventListener('click', () => {
      const idx = parseInt(card.dataset.slideTarget, 10);
      showSlide(idx);
    });
  });

  const startAutoplay = () => {
    stopAutoplay();
    autoplayTimer = setInterval(() => {
      showSlide(currentSlide + 1);
    }, 6500);
  };

  const stopAutoplay = () => {
    if (autoplayTimer) {
      clearInterval(autoplayTimer);
      autoplayTimer = null;
    }
  };

  if (featuredBox) {
    featuredBox.addEventListener('mouseenter', stopAutoplay);
    featuredBox.addEventListener('mouseleave', startAutoplay);
    startAutoplay();
  }

  // 6. Back-to-Top Button
  const backToTopBtn = document.getElementById('back-to-top-btn');
  if (backToTopBtn) {
    backToTopBtn.addEventListener('click', () => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
  }

  // 7. Smooth Scroll for Anchor Links
  const navLinks = document.querySelectorAll('.desktop-nav-link, .drawer-link, .footer-link, .hero-actions-group a, .about-action a, .why-action a, .modern-cta-actions a');
  navLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      const href = this.getAttribute('href');
      if (href && href.startsWith('#') && href.length > 1) {
        const target = document.querySelector(href);
        if (target) {
          e.preventDefault();
          const headerHeight = header ? header.offsetHeight : 70;
          const targetPos = target.getBoundingClientRect().top + window.pageYOffset - headerHeight;
          window.scrollTo({
            top: targetPos,
            behavior: 'smooth'
          });
        }
      }
    });
  });

  // 8. Interactive Marquee Pause/Play on Arrow Buttons
  const marqueeRows = document.querySelectorAll('.marquee-row-container');
  marqueeRows.forEach(row => {
    const arrows = row.querySelectorAll('.marquee-arrow-btn');
    const track = row.querySelector('.marquee-track');
    arrows.forEach(btn => {
      btn.addEventListener('click', () => {
        if (!track) return;
        track.style.animationPlayState = 'paused';
        setTimeout(() => {
          track.style.animationPlayState = 'running';
        }, 2500);
      });
    });
  });
});
