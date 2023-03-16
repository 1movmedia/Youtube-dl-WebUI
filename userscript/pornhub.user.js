// ==UserScript==
// @name        Pornhub.com >_dlp UI
// @namespace   Azazar's Scripts
// @match       https://rt.pornhub.com/*
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

function isReady() {
    if (S('v-more-action-menu')) {
        return !!D.querySelector('v-more-action-menu div.moreActionMenu');
    }

    return !!S('li.pcVideoListItem');
}

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
    [ "url", "uploaderUrl", "uploader" ].forEach(key => videoInfo[key] = video[key]);

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

function attachDownloader() {
    document.querySelectorAll('li.pcVideoListItem').forEach(li => {
        let channelAnchor = li.querySelector('.usernameWrap a');
    
        let video = {
            vkey: li.dataset.videoVkey,
            url: 'https://www.pornhub.com/view_video.php?viewkey=' + li.dataset.videoVkey,
            uploaderUrl: channelAnchor.href,
            uploader: channelAnchor.innerText,
        };

        console.log('Video:', video);
    
        unsafeWindow.li = li;

        let iconClass = 'ph-icon-cloud-download';

        let onDownloaded = span => {
            span.classList.remove(iconClass);
            span.classList.add(iconDoneClass);
            GM_setValue(`downloaded(${video.vkey})`, true);
        };

        if (GM_getValue(`downloaded(${video.vkey})`)) {
            iconClass = 'ph-icon-cloud-done';
        }

        let iconDoneClass = 'ph-icon-cloud-done';

        let vidTitleWrapper = li.querySelector('div.vidTitleWrapper');

        if (vidTitleWrapper) {
            let downloadButton = H(
               `<div class="rightAlign">
                    <span class="${iconClass}"></span>
                </div>`);
    
            vidTitleWrapper.appendChild(downloadButton);
    
            downloadButton.onclick = e => {
                download(video, () => {
                    downloadButton.onclick = null;
                    onDownloaded(downloadButton.querySelector('span'))
                });
            };

            return;
        }

        let videoDetailsBlock = li.querySelector('.videoDetailsBlock div');

        if (videoDetailsBlock) {
            let downloadButton = H(
               `<div class="rating-container neutral">
                    <span style="cursor: pointer" class="${iconClass}"></span>
                </div>`);
     
            videoDetailsBlock.appendChild(downloadButton);
     
             downloadButton.onclick = e => {
                 download(video, () => {
                     downloadButton.onclick = null;
                     onDownloaded(downloadButton.querySelector('span'))
                 });
             };
 
             return;
         }

         console.error("Can't attach download button to", li);
    });
}

let readyTimer = setInterval(() => {
    if (isReady()) {
        attachDownloader();

        clearInterval(readyTimer);
    }
}, 100);

GM_registerMenuCommand('Clear >_dlp url', () => {
    ytDlpUrl = null;
    GM_setValue('ytDlpUrl', null);
    GM_deleteValue('ytDlpUrl');
})

console.log("ytDlpUrl:", ytDlpUrl);
