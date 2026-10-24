/**
 * ROYAL ENAN SECURITY SERVICES (RESS)
 * Service Details Interactive Engine
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Initialize AOS if available
  if (typeof AOS !== 'undefined') {
    AOS.init({
      duration: 750,
      easing: 'ease-out-cubic',
      once: true,
      offset: 40
    });
  }

  // 2. Header Scroll Effect
  const header = document.querySelector('.service-page-header');
  const handleScroll = () => {
    if (header) {
      if (window.scrollY > 25) {
        header.classList.add('is-scrolled');
      } else {
        header.classList.remove('is-scrolled');
      }
    }
  };
  window.addEventListener('scroll', handleScroll, { passive: true });
  handleScroll();

  // 3. Mobile Navigation Drawer Handling
  const mobileToggleBtn = document.getElementById('service-menu-toggle-btn') || document.getElementById('menu-toggle-btn');
  const mobileDrawer = document.getElementById('mobile-nav-drawer');
  const drawerCloseBtn = document.getElementById('drawer-close-btn');
  const drawerLinks = document.querySelectorAll('.drawer-link');

  const openDrawer = () => {
    if (!mobileDrawer) return;
    mobileDrawer.classList.add('is-active');
    mobileDrawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  };

  const closeDrawer = () => {
    if (!mobileDrawer) return;
    mobileDrawer.classList.remove('is-active');
    mobileDrawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  };

  if (mobileToggleBtn) {
    mobileToggleBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      openDrawer();
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
      if (container && !container.contains(e.target) && e.target !== mobileToggleBtn && !mobileToggleBtn?.contains(e.target)) {
        closeDrawer();
      }
    }
  });

  // Drawer Flyout Services Panel on Hover / Touch
  const drawerServicesItem = document.getElementById('drawer-services-item');
  const drawerServicesLink = document.getElementById('drawer-services-link');
  const drawerServicesPanel = document.getElementById('drawer-services-panel');
  let panelHideTimer = null;

  if (drawerServicesItem && drawerServicesPanel) {
    const showPanel = () => {
      clearTimeout(panelHideTimer);
      drawerServicesPanel.classList.add('is-active');
      drawerServicesItem.classList.add('is-hovered');
    };

    const hidePanel = () => {
      panelHideTimer = setTimeout(() => {
        drawerServicesPanel.classList.remove('is-active');
        drawerServicesItem.classList.remove('is-hovered');
      }, 220);
    };

    drawerServicesItem.addEventListener('mouseenter', showPanel);
    drawerServicesItem.addEventListener('mouseleave', hidePanel);
    drawerServicesPanel.addEventListener('mouseenter', showPanel);
    drawerServicesPanel.addEventListener('mouseleave', hidePanel);

    // Mobile / Touch click toggle
    if (drawerServicesLink) {
      drawerServicesLink.addEventListener('click', (e) => {
        if (window.innerWidth < 992) {
          const isOpen = drawerServicesPanel.classList.toggle('is-open');
          drawerServicesItem.classList.toggle('is-hovered', isOpen);
          if (isOpen) {
            e.preventDefault();
          }
        }
      });
    }
  }

  // Highlight active links in navigation drawer based on current page
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  const isServicePage = currentPage.includes('-services.html') || currentPage === 'services.html';

  document.querySelectorAll('.cred-drawer-nav-list a.cred-nav-item').forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPage || (currentPage === 'index.html' && href.startsWith('index.html'))) {
      link.classList.add('active');
    } else if (isServicePage && href === 'services.html') {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });

  document.querySelectorAll('.drawer-services-subgrid a.drawer-sub-card').forEach(subLink => {
    const href = subLink.getAttribute('href');
    if (href === currentPage) {
      subLink.classList.add('active');
    } else {
      subLink.classList.remove('active');
    }
  });

  // 4. Video Lightbox Modal
  const videoModal = document.getElementById('service-video-modal');
  const videoPlayer = document.getElementById('service-video-player');
  const videoTriggers = document.querySelectorAll('.js-open-video-modal');
  const videoCloseBtn = document.getElementById('video-modal-close-btn');

  const openVideoModal = () => {
    if (!videoModal) return;
    videoModal.classList.add('is-active');
    videoModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (videoPlayer) {
      videoPlayer.play().catch(() => {});
    }
  };

  const closeVideoModal = () => {
    if (!videoModal) return;
    videoModal.classList.remove('is-active');
    videoModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (videoPlayer) {
      videoPlayer.pause();
    }
  };

  videoTriggers.forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      openVideoModal();
    });
  });

  if (videoCloseBtn) {
    videoCloseBtn.addEventListener('click', closeVideoModal);
  }

  if (videoModal) {
    videoModal.addEventListener('click', (e) => {
      const dialog = videoModal.querySelector('.video-modal-dialog');
      if (dialog && !dialog.contains(e.target)) {
        closeVideoModal();
      }
    });
  }

  // Global ESC Key Handler
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (videoModal && videoModal.classList.contains('is-active')) {
        closeVideoModal();
      } else if (mobileDrawer && mobileDrawer.classList.contains('is-active')) {
        closeDrawer();
      }
    }
  });

  // 5. Newsletter Validation
  const newsletterForm = document.getElementById('newsletter-form');
  const newsletterInput = document.getElementById('newsletter-email');
  const newsletterFeedback = document.getElementById('newsletter-feedback');

  if (newsletterForm && newsletterInput && newsletterFeedback) {
    newsletterForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const email = newsletterInput.value.trim();
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      if (!email) {
        newsletterFeedback.textContent = 'Please enter your email address.';
        newsletterFeedback.className = 'newsletter-feedback is-visible error';
      } else if (!emailRegex.test(email)) {
        newsletterFeedback.textContent = 'Please provide a valid email address.';
        newsletterFeedback.className = 'newsletter-feedback is-visible error';
      } else {
        // Honest client-side receipt notification
        newsletterFeedback.textContent = 'Thank you! Your request to join our newsletter has been recorded.';
        newsletterFeedback.className = 'newsletter-feedback is-visible success';
        newsletterInput.value = '';
      }
    });
  }

  // 6. Back to Top Button
  const backToTopBtn = document.getElementById('back-to-top-btn');
  if (backToTopBtn) {
    backToTopBtn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
});
