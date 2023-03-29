// ==UserScript==
// @name        Wheel Video Navigator
// @namespace   Azazar's Scripts
// @match       *://*/*
// @grant       none
// @version     0.1
// @author      -
// @description Handle mouse wheel events to navigate videos
// ==/UserScript==

(function() {
    'use strict';
  
    const skipTime = 1; // Time to skip in seconds
  
    function attachWheelListener(element, video) {
      if (element._wheelScrolled) {
        return;
      }
  
      element.addEventListener('wheel', (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (event.deltaY < 0) {
          // Scroll up to go forward
          video.currentTime += skipTime;
        } else {
          // Scroll down to go backward
          video.currentTime -= skipTime;
        }
      }, { passive: false });
  
      element._wheelScrolled = true;
    }
  
    function isAncestor(parent, child) {
      let currentNode = child.parentNode;
      while (currentNode) {
        if (currentNode === parent) {
          return true;
        }
        currentNode = currentNode.parentNode;
      }
      return false;
    }
  
    function processVideos() {
      document.querySelectorAll('video').forEach(video => {
        const rect = video.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
  
        const coveringElements = document.elementsFromPoint(centerX, centerY);
  
        coveringElements.forEach(elem => {
          if (elem !== video && !isAncestor(elem, video)) {
            attachWheelListener(elem, video);
          }
        });
      });
    }
  
    // Observe DOM changes to attach event listeners to new video elements
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'childList') {
          processVideos();
        }
      });
    });
  
    const observerConfig = {
      attributes: false,
      childList: true,
      subtree: true,
    };
  
    observer.observe(document.body, observerConfig);
  
    // Process initial videos
    processVideos();
  })();
  