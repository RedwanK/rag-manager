import { bootstrap } from './bootstrap';
import { Helpers } from './helpers';
import { Menu } from './menu';

let menuInstance;

const initMenu = () => {
  const layoutMenuEl = document.querySelectorAll('#layout-menu');

  layoutMenuEl.forEach(element => {
    menuInstance = new Menu(element, { closeChildren: false });
    Helpers.scrollToActive(false);
    window.Helpers.mainMenu = menuInstance;
  });

  const menuTogglers = document.querySelectorAll('.layout-menu-toggle');
  menuTogglers.forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      Helpers.toggleCollapsed();
    });
  });

  const delay = (elem, callback) => {
    let timeout = null;
    elem.onmouseenter = function onEnter() {
      timeout = setTimeout(callback, Helpers.isSmallScreen() ? 0 : 300);
    };
    elem.onmouseleave = function onLeave() {
      document.querySelector('.layout-menu-toggle')?.classList.remove('d-block');
      clearTimeout(timeout);
    };
  };

  const layoutMenu = document.getElementById('layout-menu');
  if (layoutMenu) {
    delay(layoutMenu, () => {
      if (!Helpers.isSmallScreen()) {
        document.querySelector('.layout-menu-toggle')?.classList.add('d-block');
      }
    });
  }

  const menuInnerContainer = document.getElementsByClassName('menu-inner');
  const menuInnerShadow = document.getElementsByClassName('menu-inner-shadow')[0];
  if (menuInnerContainer.length > 0 && menuInnerShadow) {
    menuInnerContainer[0].addEventListener('ps-scroll-y', function onScroll() {
      menuInnerShadow.style.display = this.querySelector('.ps__thumb-y')?.offsetTop ? 'block' : 'none';
    });
  }
};

const initTooltips = () => {
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(triggerEl => new bootstrap.Tooltip(triggerEl));
};

const initAccordions = () => {
  const accordionActiveFunction = e => {
    if (e.type === 'show.bs.collapse') {
      e.target.closest('.accordion-item')?.classList.add('active');
    } else {
      e.target.closest('.accordion-item')?.classList.remove('active');
    }
  };

  const accordionTriggerList = [].slice.call(document.querySelectorAll('.accordion'));
  accordionTriggerList.map(triggerEl => {
    triggerEl.addEventListener('show.bs.collapse', accordionActiveFunction);
    triggerEl.addEventListener('hide.bs.collapse', accordionActiveFunction);
    return triggerEl;
  });
};

const initThemeScripts = () => {
  initMenu();
  initTooltips();
  initAccordions();

  Helpers.setAutoUpdate(true);
  Helpers.initPasswordToggle();
  Helpers.initSpeechToText();

  if (!Helpers.isSmallScreen()) {
    Helpers.setCollapsed(true, false);
  }
};

if (typeof window !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeScripts);
  } else {
    initThemeScripts();
  }
}
