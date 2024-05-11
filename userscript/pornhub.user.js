// ==UserScript==
// @name        Pornhub.com >_dlp UI
// @namespace   Azazar's Scripts
// @match       https://*.pornhub.com/view_video.php*
// @match       https://pornhub.com/view_video.php*
// @version     0.3
// @author      Azazar <https://github.com/azazar/>
// @grant       GM.xmlHttpRequest
// @grant       GM_getValue
// @grant       GM_setValue
// @grant       GM_deleteValue
// @grant       GM_registerMenuCommand
// @grant       GM_addStyle
// @grant       GM_openInTab
// ==/UserScript==

let ytDlpUrl = GM_getValue('ytDlpUrl');

const D = document;
const S = s => D.querySelector(s);
const A = s => D.querySelectorAll(s);
const H = html => {
    let el = D.createElement('span');
    el.innerHTML = html;
    return el.childNodes[0];
};

const log = () => {};

async function download(video, onsuccess) {
    log('Download request for video', video);

    // Get video details
    let videoInfo;

    let video_url = document.querySelector('meta[property="og:url"]').content;

    let video_id;

    let match;

    if (!(match = /viewkey=([^&]+)/.exec(video_url))) {
        throw new Error(`Failed to extract ID from URL ${video_url}`);
    }

    video_id = match[1];

    log(match);

    videoInfo = {
        "id": "ph-" + video_id,
        "video_id": video_id,
        "title": document.querySelector('meta[property="og:title"]').content,
       
        "tags": Array.from(document.querySelectorAll('.tagsWrapper a[data-label="Tag"]')).map(a => ({'tag_name': a.innerText})),
        "pornstars": Array.from(document.querySelectorAll('a[data-mxptype="Pornstar"][data-mxptext]')).map(a => ({'pornstar_name': a.dataset.mxptext})),
        "categories": dataLayer.filter(e => !!e.videodata)[0].videodata.categories_in_video.split(',').map(c => ({ category: c })),
    };

    if (videoInfo.pornstars.length === 0 && (/\/model\/[^/]+$/.test(video.userUrl))) {
        videoInfo.pornstars = [{'pornstar_name': video.userTitle}];
    }

    // Copy fields not available via API
    [ "url", "userTitle", "userType", "userUrl", "cutFrom", "cutTo", "duration", "cutEnd", "target" ].forEach(key => videoInfo[key] = video[key]);

    log('videoInfo:', videoInfo);

    // Submit download request

    GM.xmlHttpRequest({
        method: 'POST',
        url: ytDlpUrl + '/download.php',
        headers: {
            "accept": "application/json",
            "content-type": "application/json",
        },
        data: JSON.stringify(videoInfo),
        onload: function(response) {
            if (response.status >= 200 && response.status < 300) {
                let responseObj = JSON.parse(response.responseText);

                if (responseObj.success) {
                    if (onsuccess) {
                        onsuccess(responseObj);
                    }
                }
                else {
                    alert(responseObj.errors.join("\n"));
                }
            }
            else {
                alert('Request failed with status ' + response.status);
            }
        },
        onerror: function(error) {
            alert('Request failed with error ' + error);
        }
    });
}

if (location.search.startsWith('?viewkey=')) {
    (async () => {

        let viewKey = location.search.substring('?viewkey='.length).split('&')[0];

        let usernameWrap = S('.video-detailed-info .usernameWrap');
        let userLink = usernameWrap.querySelector('a[href]');

        let video = {
            vkey: viewKey,
            url: 'https://www.pornhub.com/view_video.php?viewkey=' + viewKey,
            userType: usernameWrap.dataset.type,
            userTitle: userLink.innerText,
            userUrl: userLink.href,
        };

        log("Video:", video);

        let videoId = 'ph-' + video.vkey;

        let state = null;

        let isDownloaded = false;

        try {
            log('Fetching...');

            state = await (new Promise((resolve, reject) => {
                GM.xmlHttpRequest({
                    method: 'POST',
                    url: ytDlpUrl + '/prepare.php',
                    headers: {
                        "accept": "application/json",
                        "content-type": "application/x-www-form-urlencoded",
                    },
                    data: `id=${videoId}`,
                    onload: function(response) {
                        if (response.status >= 200 && response.status < 300) {
                            resolve(JSON.parse(response.responseText));
                        }
                        else {
                            reject(new Error('Request failed with status ' + response.status));
                        }
                    },
                    onerror: function(error) {
                        reject(new Error('Request failed with error ' + error));
                    }
                });
            }));

            isDownloaded = state.downloaded;
        }
        catch (e) {
            console.error("YOUTUBE-DL: Failed fetching video status from downloader:", e);
        }

        log("State:", state);

        let iconDownloadClass = 'ph-icon-cloud-download';
        let iconDownloadedClass = 'ph-icon-cloud-done';

        let buttons = [
            {
                iconClass: 'ph-icon-arrow-back',
                caption: '',
                onclick: (e, btn) => {
                    let videoElement = S('#player video');
                    videoElement.currentTime -= 1;
                }
            },
            {
                iconClass: 'ph-icon-chevron-left',
                caption: '',

                onclick: (e, btn) => {
                    let videoElement = S('#player video');
                    videoElement.currentTime -= (1/25);
                }
            },
            {
                iconClass: 'ph-icon-chevron-right',
                caption: '',
                onclick: (e, btn) => {
                    let videoElement = S('#player video');
                    videoElement.currentTime += (1/25);
                }
            },
            {
                iconClass: 'ph-icon-arrow-forward',
                caption: '',
                onclick: (e, btn) => {
                    let videoElement = S('#player video');
                    videoElement.currentTime += 1;
                }
            },
            {
                buttonClass: 'signedout',
                iconClass: 'ph-icon-login',
                caption: 'Log In',
                onclick: (e, btn) => {
                    GM_openInTab(ytDlpUrl + '/login.php');
                }
            },
            {
                buttonClass: 'signedin downloadvideo',
                iconClass: 'ph-icon-crop',
                caption: 'Mark Start',
                onclick: (e, btn) => {
                    let videoElement = S('#player video');
                    let time = videoElement.currentTime;
                    let duration = videoElement.duration;
                    video['cutFrom'] = time;
                    video['duration'] = duration;
                    btn.captionElement.innerText = `Mark Start (${Math.round(time)})`;
                },
            },
            {
                buttonClass: 'signedin downloadvideo',
                iconClass: 'ph-icon-crop',
                caption: 'Mark End',
                onclick: (e, btn) => {
                    let videoElement = S('#player video');
                    let time = videoElement.currentTime;
                    let duration = videoElement.duration;
                    video['cutEnd'] = duration - time;
                    video['cutTo'] = time;
                    video['duration'] = duration;
                    btn.captionElement.innerText = `Mark End (${Math.round(video['cutEnd'])})`;
                },
            },
            {
                buttonClass: 'signedin downloadvideo',
                iconClass: isDownloaded ? iconDownloadedClass : iconDownloadClass,
                caption: 'Download',
                onclick: (e, btn) => {
                    if ((video.cutFrom !== undefined || video.cutEnd !== undefined)) {
                        if (video.cutFrom === undefined) {
                            alert('Begin Mark is not set');
                            return;
                        }
                        if (video.cutEnd === undefined) {
                            alert('End Mark is not set');
                            return;
                        }
                        if (video.cutFrom + video.cutEnd > video.duration) {
                            alert('Begin mark is set after end mark');
                            return;
                        }
                    }

                    log("Download:", video);

                    // let btn = this;

                    download(video, () => {
                        log('button:', btn);
                        btn.buttonElement.onclick = null;
                        btn.iconElement.classList.remove(iconDownloadClass);
                        btn.iconElement.classList.add(iconDownloadedClass);
                        controlEl.classList.add('downloaded');
                        controlEl.classList.remove('newvideo')

                    });
                },
            },
        ];

        let titleContainer = S('.title-container');
        let controlEl = H(`<div class="userscript-ui-container"></div>`);

        titleContainer.parentElement.insertBefore(controlEl, titleContainer);

        let loggedIn = !!state;

        controlEl.classList.add(loggedIn ? 'signedin' : 'signedout');
        controlEl.classList.add(isDownloaded ? 'downloaded' : 'newvideo')

        if (loggedIn && state.targets) {
            if (state.targets !== null && state.targets.length > 0) {
                let selectedTarget = GM_getValue('selectedTarget');
                let targetSelect = H(
                    '<select class="userscript-ui-input signedin downloadvideo">' +
                    state.targets.map(s => `<option${selectedTarget == s ? ' selected' : ''} value="${s}">${s}</option>`).join('') +
                    '</select>');

                targetSelect.onchange = e => {
                    let newTarget = targetSelect.options[targetSelect.selectedIndex].value;
                    video.target = newTarget;
                    GM_setValue('selectedTarget', newTarget);
                };

                video.target = targetSelect.options[targetSelect.selectedIndex].value;

                controlEl.insertBefore(targetSelect, controlEl.childNodes[0]);
            }
    }

        let el = H('<span class="userscript-ui-message downloadedvideo">This video was scheduled for download</span>');

        controlEl.appendChild(el);

        buttons.forEach(button => {
            let btnCell = H(`<span class="userscript-ui-menuitem ${button.buttonClass}"><i class="${button.iconClass}"></i><span class="userscript-ui-caption">${button.caption}</span></span>`);

            button.buttonElement = btnCell;
            button.iconElement = btnCell.querySelector('i');
            button.captionElement = btnCell.querySelector('span.userscript-ui-caption');

            controlEl.appendChild(btnCell);

            btnCell.onclick = e => button.onclick(e, button);

            log('Added Button:', btnCell);
        });
    })();
}

window.onkeydown = function(e) {
    if (e.keyCode == 65) {
        let videoElement = S('#player video');
        videoElement.currentTime -= 1;
    }

    if (e.keyCode == 68) {
        let videoElement = S('#player video');
        videoElement.currentTime += 1;
    }
};

GM_addStyle(`
   .userscript-ui-container {
        padding: 10px 10px 12px;
        text-align: right;
        display: flex;

        user-select: none;
    }

    .userscript-ui-container.signedout .signedin,
    .userscript-ui-container.signedin .signedout,
    .userscript-ui-container.downloaded .downloadvideo,
    .userscript-ui-container.newvideo .downloadedvideo {
        display: none;
    }

    .userscript-ui-input {
        padding-left: 10px;
        font-size: 14px;
        border: 1px solid #aaa;
        border-radius: 4px;
        background: #fff;
        color: #333;
        margin-right: auto;
    }

    .userscript-ui-message {
        padding: 3px 7px 3px 7px;
        font-size: 14px;
        border: 1px solid #aaa;
        border-radius: 4px;
        background: #f44;
        color: #000;
        margin-left: auto;
    }

    .userscript-ui-menuitem {
        padding-left: 10px;
        color: #c6c6c6;
        cursor: pointer;
    }

    .userscript-ui-menuitem:hover {
        color: #fff;
    }

    .userscript-ui-caption {
        padding-left: 10px;
    }
`);
