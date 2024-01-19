// This file is part of the Zoom plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Toggles text to be shown when a user hits 'Show More' and
 * hides text when user hits 'Show Less'
 *
 * @copyright  2020 UC Regents, 2023 Giorgio Consorti
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {


  const init = (zoomObj, userObj, zoomMeetingSdkWebVersion, debugging) => {

    const debug = debugging || false;

    const decodeEntity = (inputStr) => {
      var textarea = document.createElement('textarea');
      textarea.innerHTML = inputStr;
      return textarea.value;
    };

    const log = function () {
      if (debug) {
        const LOG_PREFIX = () => "**** LYNX **** " + new Date().toLocaleString() + ": ";
        var args = Array.from(arguments);
        args.unshift(LOG_PREFIX());
        window.console.log.apply(window.console, args);
      }
    };

    const error = function () {
      if (debug) {
        const LOG_PREFIX = () => "**** LYNX **** " + new Date().toLocaleString() + ": ";
        var args = Array.from(arguments);
        args.unshift(LOG_PREFIX());
        window.console.error.apply(window.console, args);
      }
    };

    const initCallBack = function (isError, response) {

      window.parent.postMessage({
        message: "zoomInitDone",
        userishost: zoomObj.userishost,
        debugging: debug,
      });

      if (!isError) {
        log("intResponse is ", response);
        window.ZoomMtg.join({
          signature: zoomObj.signature,
          sdkKey: zoomObj.sdkKey,
          meetingNumber: zoomObj.meeting_id,
          userName: userObj.fullname,
          userEmail: userObj.email,
          // password optional; set by Host
          passWord: zoomObj.password,
          tk: zoomObj.tk,
          zak: zoomObj.zak,
          success: function (joinResp) {
            if (!zoomObj.userishost) {
              // leave recording indication only, for privacy issues
              Array.from(document.getElementsByClassName('meeting-info-container--left-side') ?? []).forEach(
                (el) => {
                  Array.from(el.childNodes).filter(
                    (child) => !child.classList.contains('recording-indication__recording-container')
                  ).forEach(
                    (el) => { el.style.display = 'none'; }
                  );
                }
              );
              // Remove Captions buttons for non-host
              Array.from(document.querySelectorAll(
                '[feature-type="newLTT"]') ?? []
              ).forEach(
                (el) => {
                  el.remove();
                }
              );
            }
            window.parent.postMessage({
              message: "zoomJoined",
              userishost: zoomObj.userishost,
              debugging: debug,
            });
            addZoomSessionKeys();
            log("joinResp is ", joinResp);
          },
          error: function (joinResp) {
            error("joinResp is ", joinResp);
          }
        });
      } else {
        error("intResponse is ", response);
      }
    };

    const startMeeting = () => {

      document.getElementById('zmmtg-root').style.display = 'block';

      log('init meeting with zoom object', zoomObj);
      log('init meeting with user object', userObj);
      log('Meeting SDK', window.ZoomMtg.getWebSDKVersion());
      log('checkFeatureRequirements', window.ZoomMtg.checkFeatureRequirements());

      window.ZoomMtg.init({
        // see: https://marketplacefront.zoom.us/sdk/meeting/web/modules.html#initArgs
        // and  https://marketplacefront.zoom.us/sdk/meeting/web/components/index.html
        leaveUrl: decodeEntity(zoomObj.leaveUrl),
        debug: debug,
        disableCORP: !window.crossOriginIsolated,
        enableHD: true,
        enableFullHD: true,
        isSupportAV: true,
        showMeetingHeader: zoomObj.userishost,
        // disableVoIP: true,
        videoHeader: zoomObj.userishost,
        isSupportBreakout: true,
        isSupportCC: false, // zoomObj.userishost,
        isSupportChat: true,
        isSupportNonverbal: zoomObj.userishost,
        isSupportPolling: zoomObj.userishost,
        isSupportQA: zoomObj.userishost,
        // isLockBottom: true,
        disableCallOut: !zoomObj.userishost,
        disableInvite: !zoomObj.userishost,
        // disablePreview: true,
        disableRecord: !zoomObj.userishost,
        disableReport: !zoomObj.userishost,
        screenShare: zoomObj.userishost,
        meetingInfo: (zoomObj.userishost ? [
          'topic',
          'host',
          'participant',
          'mn', // meeting number
          'dc',
          'pwd',
          // 'telPwd',
          'invite',
          'enctype',
          // 'report',
        ] : []),
        success: function (initResp) {
          initCallBack(false, initResp);
        },
        error: (initResp) => {
          initCallBack(true, initResp);
        }
      });
    };

    const addZoomSessionKeys = () => {
      const addedKeys = getNewSessionKeys(sessionKeys);
      log("sessionStorage items added by zoom", addedKeys);
      if (addedKeys.length) {
        const zoomKeys = (window.sessionStorage.getItem('zoomKeys') ?? '').split(',').filter(el => el.length > 0);
        addedKeys.forEach((key) => {
          if (!zoomKeys.includes(key)) {
            zoomKeys.push(key);
          }
        });
        if (zoomKeys.length > 0) {
          log('storing zoomKeys', zoomKeys);
          window.sessionStorage.setItem('zoomKeys', zoomKeys.join(','));
        } else {
          log('removing zoomKeys since it was an empty array');
          window.sessionStorage.removeItem('zoomKeys');
        }
      }
    };

    const getSessionKeys = () => {
      let retval = [];
      Object.keys(window.sessionStorage).forEach((key) => retval.push(key));
      return retval;
    };

    const getNewSessionKeys = (startArr) => getSessionKeys().filter(x => !startArr.includes(x));

    log('**********************************************');

    /**
     * get keys stored in sessionStorage.
     * The init/success callback will add a new session key
     * called 'zoomKeys' containig only the keys added by zoom.
     *
     * When the user logs out from moodle, the zoom_handler::loggedout
     * php method will take care of deleting them, actually logging out
     * the user from zoom.
     */
    const sessionKeys = getSessionKeys();

    let language = userObj.lang || 'en-US';
    if (-1 === language.indexOf('-')) {
      language = language.toLowerCase() + '-' + (language !== 'en' ? language.toUpperCase() : 'US');
    }

    /**
     * WARNING!!!
     * Don't you dare removing https and starting the url with '//'.
     * The lib is not going to work!
     */
    window.ZoomMtg.setZoomJSLib(`https://source.zoom.us/${zoomMeetingSdkWebVersion}/lib`, '/av');
    window.ZoomMtg.preLoadWasm();

    if ('function' == typeof window.ZoomMtg.prepareWebSDK) {
      window.ZoomMtg.prepareWebSDK();
    } else {
      window.ZoomMtg.prepareJssdk();
    }
    // // loads language files, also passes any error messages to the ui
    window.ZoomMtg.i18n.load(language);
    window.ZoomMtg.i18n.reload(language);
    // window.ZoomMtg.reRender({ lang: language });

    startMeeting();

    log('**********************************************');
  };

  // add event listeners
  if (window.self == window.top) {
    /**
     * add event listener for the main page, i.e. outside the iframe
     */
    window.addEventListener("message", (event) => {
      const meetingEl = document.getElementById('meetingSDKElement');
      if (event.data.message === 'zoomLeave') {
        // zoomLeave, redirect to passed url
        if ('zoomLeave' in event.data) {
          if ('debugging' in event.data && event.data.debugging && 'userishost' in event.data && event.data.userishost) {
            // replace the iframe with a back link.
            // const iframeParent = document.getElementById('meetingSDKElement').parentNode;
            // iframeParent.style.margin = "0";
            // iframeParent.style.padding = "0";
            if (meetingEl && meetingEl.parentNode) {
              meetingEl.parentNode.classList.remove(...meetingEl.parentNode.classList);
              meetingEl.parentNode.innerHTML = `<a href="${event.data.zoomLeave}">${event.data.zoomLeave}</a>`;
            }
          } else {
            window.location.replace(event.data.zoomLeave);
          }
        }
      } else if (event.data.message === 'zoomInitDone') {
        if (meetingEl) {
          if (!('debugging' in event.data && event.data.debugging)) {
            meetingEl.removeAttribute('onload');
          }
        }
      } else if (event.data.message === 'zoomJoined') {
        if (meetingEl) {
          meetingEl.parentNode.classList.add('embed-responsive', 'embed-responsive-16by9', 'joined');
        }
      }
    }, false);
  } else {
    /**
     * add event listener for the zoom meeting, i.e. inside the iframe
     */
    window.addEventListener("message", (event) => {
      // messages handled inside the iframe
      if (event.data.message === 'init') {
        // trigger the init function
        init(event.data.zoom, event.data.user, event.data.zoomSdkVersion, event.data.debugging);
      }
    }, false);
  }

})();
