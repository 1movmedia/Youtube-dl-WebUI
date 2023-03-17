// ==UserScript==
// @name        Pornhub.com >_dlp UI
// @namespace   Azazar's Scripts
// @match       https://*.pornhub.com/view_video.php*
// @match       https://pornhub.com/view_video.php*
// @version     0.1
// @author      Azazar <https://github.com/azazar/>
// @require     https://unpkg.com/gmxhr-fetch
// @grant       GM_xmlhttpRequest
// @grant       GM.xmlHttpRequest
// @require     https://cdn.jsdelivr.net/npm/js-base64@3.7.5/base64.min.js
// @grant       GM_getValue
// @grant       GM_setValue
// @grant       GM_deleteValue
// @grant       GM_registerMenuCommand
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
    while (!ytDlpUrl) {
        let url = prompt('Provide >_dlp url');

        if (url === null) {
            console.log('No >_dlp url provided');

            return;
        }

        if (/^https?:\/\//.test(url)) {
            ytDlpUrl = url;

            GM_setValue('ytDlpUrl', ytDlpUrl);

            break;
        }
    }
    console.log('Downloading', video);
    
    let videoInfoResponse = await fetch('https://rt.pornhub.com/webmasters/video_by_id?id=' + video.vkey);

    let videoInfo = (await videoInfoResponse.json()).video;

    // Copy fields not available via API
    [ "url", "userTitle", "userType", "userUrl", "cutFrom", "cutTo" ].forEach(key => videoInfo[key] = video[key]);

    console.log('videoInfo:', videoInfo);

    // Submit download request

    let metadata = {};
    metadata[videoInfo.url] = videoInfo;

    let response = await gmfetch(ytDlpUrl + '/index.php', {
        "headers": {
            "accept": "application/json",
            "content-type": "application/x-www-form-urlencoded",
        },
        "body": "urls=" + encodeURIComponent(videoInfo.url) + "&outfilename=&vformat=&metadata=" + Base64.encode(JSON.stringify(metadata)),
        "method": "POST",
    });

    if (!response.ok) {
        let responseText = await response.text();
    
        console.error("Response:", responseText);

        return;
    }

    if (onsuccess) {
        onsuccess(response);
    }
}

if (location.search.startsWith('?viewkey=')) {
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

    let downloadKey = `downloaded(${video.vkey})`;
    let isDownloaded = GM_getValue(downloadKey);
    let setDownloaded = () => GM_setValue(downloadKey, true);

    console.log("Video:", video);

    let iconDownloadClass = 'ph-icon-cloud-download';
    let iconDownloadedClass = 'ph-icon-cloud-done';

    let buttons = [
        {
            iconClass: isDownloaded ? iconDownloadedClass : iconDownloadClass,
            caption: 'Download',
            onclick: e => {
                if ((video.cutFrom || video.cutTo)) {
                    if (!video.cutFrom) {
                        alert('Begin Mark is not set');
                        return;
                    }
                    if (!video.cutTo) {
                        alert('End Mark is not set');
                        return;
                    }
                    if (video.cutFrom > video.cutTo) {
                        alert('Begin mark is set after end mark');
                        return;
                    }
                }

                console.log("Download:", video);

                let btn = this;

                download(video, () => {
                    setDownloaded();
                    btn.buttonElement.onclick = null;
                    btn.iconElement.classList.remove(iconDownloadClass);
                    btn.iconElement.add(iconDownloadedClass);
                });
            },
        },
        {
            iconClass: 'ph-icon-crop',
            caption: 'Mark Start',
            onclick: e => {
                let playerVideoElement = document.querySelector('#player video');
                video['cutFrom'] = playerVideoElement.currentTime;
            },
        },
        {
            iconClass: 'ph-icon-crop',
            caption: 'Mark End',
            onclick: e => {
                let playerVideoElement = document.querySelector('#player video');
                video['cutTo'] = playerVideoElement.currentTime;
            },
        },
    ];

    let tabMenuWrapperRow = S('.video-actions-menu .tab-menu-wrapper-row');

    buttons.forEach(button => {
        let btnCell = H(`<div class="tab-menu-wrapper-cell"><div class="tab-menu-item"><i style="margin-right: 15px" class="${button.iconClass}"></i><span>${button.caption}</span></div></div>`);
        
        button.buttonElement = btnCell;
        button.iconElement = btnCell.querySelector('i');
    
        tabMenuWrapperRow.appendChild(btnCell);
    
        btnCell.onclick = e => button.onclick(e);
    });

}

GM_registerMenuCommand('Clear >_dlp url', () => {
    ytDlpUrl = null;
    GM_setValue('ytDlpUrl', null);
    GM_deleteValue('ytDlpUrl');
})

console.log("ytDlpUrl:", ytDlpUrl);
