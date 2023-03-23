// ==UserScript==
// @name        Pornhub.com >_dlp UI
// @namespace   Azazar's Scripts
// @match       https://*.pornhub.com/view_video.php*
// @match       https://pornhub.com/view_video.php*
// @version     0.2
// @author      Azazar <https://github.com/azazar/>
// @require     https://unpkg.com/gmxhr-fetch
// @grant       GM_xmlhttpRequest
// @grant       GM.xmlHttpRequest
// @require     https://cdn.jsdelivr.net/npm/js-base64@3.7.5/base64.min.js
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

async function download(video, onsuccess) {
    console.log('Download request for video', video);

    // Get video details
    let videoInfo;

    let video_url = document.querySelector('meta[property="og:url"]').content;

    let video_id;

    let match;

    if (!(match = /viewkey=([^&]+)/.exec(video_url))) {
        throw new Error(`Failed to extract ID from URL ${video_url}`);
    }

    video_id = match[1];

    console.log(match);

    videoInfo = {
        "video_id": video_id,
        "title": document.querySelector('meta[property="og:title"]').content,
       
        "tags": Array.from(document.querySelectorAll('.tagsWrapper a[data-label="Tag"]')).map(a => ({'tag_name': a.innerText})),
        "pornstars": Array.from(document.querySelectorAll('a[data-mxptype="Pornstar"][data-mxptext]')).map(a => ({'pornstar_name': a.dataset.mxptext})),
        "categories": dataLayer[0].videodata.categories_in_video.split(',').map(c => ({ category: c })),
    };

    if (videoInfo.pornstars.length === 0 && (/\/model\/[^/]+$/.test(video.userUrl))) {
        videoInfo.pornstars = [{'pornstar_name': video.userTitle}];
    }

    // Copy fields not available via API
    [ "url", "userTitle", "userType", "userUrl", "cutFrom", "cutTo", "duration", "cutEnd", "target" ].forEach(key => videoInfo[key] = video[key]);

    console.log('videoInfo:', videoInfo);

    // Submit download request

    let metadata = {};
    metadata[videoInfo.url] = videoInfo;

    let response = await gmfetch(ytDlpUrl + '/index.php', {
        "headers": {
            "accept": "application/json",
            "content-type": "application/x-www-form-urlencoded",
        },
        "body": "urls=" + encodeURIComponent(videoInfo.url) + "&outfilename=&vformat=&metadata=" + Base64.encodeURI(JSON.stringify(metadata)),
        "method": "POST",
    });

    if (!response.ok) {
        let responseText = await response.text();
    
        console.error("Invalid response received from", ytDlpUrl + '/index.php', "Response:", responseText);

        return;
    }

    let responseObj = await response.json();

    if (responseObj.success) {
        if (onsuccess) {
            onsuccess(responseObj);
        }
    }
    else {
        alert(responseObj.errors.join("\n"));
    }
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
    
        let isDownloaded = false;
    
        try {
            let response = await gmfetch(ytDlpUrl + '/check_id.php', {
                "headers": {
                    "accept": "application/json",
                    "content-type": "application/x-www-form-urlencoded",
                },
                "body": "id=" + video.vkey,
                "method": "POST",
            });
        
            if (!response.ok) {
                let responseText = await response.text();
            
                console.error("Invalid response received from", ytDlpUrl + '/check_id.php', "Response:", responseText);
        
                return;
            }
        
            isDownloaded = await response.json();
        }
        catch (e) {
            console.error("Failed fetching video status from downloader:", e);
        }
    
        console.log("Video:", video);
    
        let iconDownloadClass = 'ph-icon-cloud-download';
        let iconDownloadedClass = 'ph-icon-cloud-done';
    
        let buttons = [
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
    
                    console.log("Download:", video);
    
                    // let btn = this;
    
                    download(video, () => {
                        console.log('button:', btn);
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
        
        resp = await gmfetch(ytDlpUrl + "/login.php", {
            "headers": {
                "accept": "application/json",
            },
            "method": "GET",
        });
        
        let loginState = await resp.json();

        let loggedIn = loginState['logged_in'];

        controlEl.classList.add(loggedIn ? 'signedin' : 'signedout');
        controlEl.classList.add(isDownloaded ? 'downloaded' : 'newvideo')

        console.log("Download User:", loginState, loggedIn);

        if (loggedIn) {
            gmfetch(ytDlpUrl + "/targets.php", {
                "headers": {
                    "accept": "application/json",
                },
            }).then(async targetsResp => {
                let targets = await targetsResp.json();
        
                if (targets !== null && targets.length > 0) {
                    let selectedTarget = GM_getValue('selectedTarget');
                    let targetSelect = H(
                        '<select class="userscript-ui-input signedin downloadvideo">' +
                        targets.map(s => `<option${selectedTarget == s ? ' selected' : ''} value="${s}">${s}</option>`).join('') +
                        '</select>');
        
                    targetSelect.onchange = e => {
                        let newTarget = targetSelect.options[targetSelect.selectedIndex].value;
                        video.target = newTarget;
                        GM_setValue('selectedTarget', newTarget);
                    };
                    
                    video.target = targetSelect.options[targetSelect.selectedIndex].value;
        
                    controlEl.insertBefore(targetSelect, controlEl.childNodes[0]);
                }
            });
        }

        let el = H('<span class="userscript-ui-message downloadedvideo">This video is downloaded already</span>');

        controlEl.appendChild(el);

        buttons.forEach(button => {
            let btnCell = H(`<span class="userscript-ui-menuitem ${button.buttonClass}"><i class="${button.iconClass}"></i><span class="userscript-ui-caption">${button.caption}</span></span>`);
            
            button.buttonElement = btnCell;
            button.iconElement = btnCell.querySelector('i');
            button.captionElement = btnCell.querySelector('span.userscript-ui-caption');
        
            controlEl.appendChild(btnCell);
        
            btnCell.onclick = e => button.onclick(e, button);
            
            console.log('Added Button:', btnCell);
        });
    })();
}

GM_addStyle(`
   .userscript-ui-container {
        padding: 10px 10px 12px;
        text-align: right;
        display: flex;
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
