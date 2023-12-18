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

    const startMeeting = () => {

      document.getElementById('zmmtg-root').style.display = 'block';

      log('init meeting with zoom object', zoomObj);
      log('init meeting with user object', userObj);
      log('Meeting SDK', window.ZoomMtg.getWebSDKVersion());
      log(window.ZoomMtg.checkFeatureRequirements());

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
        isSupportBreakout: false,
        isSupportCC: zoomObj.userishost,
        isSupportChat: zoomObj.userishost,
        isSupportNonverbal: zoomObj.userishost,
        isSupportPolling: zoomObj.userishost,
        isSupportQA: zoomObj.userishost,
        // isLockBottom: true,
        disableCallOut: !zoomObj.userishost,
        disableInvite: !zoomObj.userishost,
        // disablePreview: true,
        disableRecord: !zoomObj.userishost,
        disableReport: !zoomObj.userishost,
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
          log("intResponse is ", initResp);
          window.ZoomMtg.join({
            signature: zoomObj.signature,
            sdkKey: zoomObj.sdkKey,
            meetingNumber: zoomObj.meeting_id,
            userName: userObj.fullname,
            userEmail: userObj.email,
            // password optional; set by Host
            passWord: zoomObj.password,
            // tk: registrantToken,
            // zak: zakToken
            success: function (joinResp) {
              if (!zoomObj.userishost) {
                Array.from(document.getElementsByClassName('meeting-info-container--left-side') ?? []).forEach((element) => {
                  element.style.display = 'none';
                });
              }
              const addedKeys = getNewSessionKeys(sessionKeys);
              if (addedKeys.length) {
                window.sessionStorage.setItem('zoomKeys', addedKeys.join(','));
              }
              log("joinResp is ", joinResp);
            },
            error: function (joinResp) {
              error("joinResp is ", joinResp);
            }
          });
        },
        error: (initResp) => {
          error("intResponse is ", initResp);
        }
      });
    };

    const getSessionKeys = () => {
      let retval = [];
      Object.keys(window.sessionStorage).forEach(function (key) {
        retval.push(key);
      });
      return retval;
    };

    const getNewSessionKeys = (startArr) => {
      let newKeys = getSessionKeys();
      return newKeys.filter(x => !startArr.includes(x));
    };

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
  window.addEventListener("message", (event) => {
    if (window.self !== window.top) {
      // messages handled inside the iframe
      if (event.data.message === 'init') {
        // trigger the init function
        init(event.data.zoom, event.data.user, event.data.zoomSdkVersion, event.data.debugging);
      }
    } else {
      // messages outside the iframe (i.e. in webmeeting.php)
      if (event.data.message === 'zoomLeave') {
        // zoomLeave, redirect to passed url
        if ('redirect' in event.data) {
          window.location.replace(event.data.redirect);
        }
      }
    }
  }, false);

})();
