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
// ==/UserScript==

let ytDlpUrl = GM_getValue('ytDlpUrl');

const D = document;
const H = html => {
    let el = D.createElement('span');
    el.innerHTML = html;
    return el.childNodes[0];
};

function isReady() {
    return D.querySelector('v-more-action-menu div.moreActionMenu') != null;
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
    [ "url", "uploaderUrl", "uploader", "uploaderType" ].forEach(key => videoInfo[key] = video[key]);

    console.log('videoInfo:', videoInfo);

    // Submit download request

    let metadata = {};
    metadata[videoInfo.url] = videoInfo;

    let response = await gmfetch(ytDlpUrl + '/index.php', {
        "headers": {
            "accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8",
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
    document.querySelectorAll('div.container ul.full-row-thumbs.display-grid li.pcVideoListItem').forEach(li => {
        let phImage = li.querySelector('div.phimage');
        let phAnchor = phImage.querySelector('a');
        let channelAnchor = li.querySelector('.usernameWrap a');
        let channelIcon = li.querySelector('.usernameWrapper span[data-title]');
    
        let video = {
            vkey: li.dataset.videoVkey,
            url: phAnchor.href,
            uploaderUrl: channelAnchor.href,
            uploader: channelAnchor.innerText,
            uploaderType: channelIcon ? channelIcon.dataset.title : null,
        };
    
        unsafeWindow.li = li;

        let vidTitleWrapper = li.querySelector('div.vidTitleWrapper');

        let iconClass = 'ph-icon-cloud-download';

        if (GM_getValue(`downloaded(${video.vkey})`)) {
            iconClass = 'ph-icon-flip-camera-ios';
        }

        let downloadButton = H(
            `<div class="rightAlign moreActionMenuButton">
                <span class="${iconClass}"></span>
            </div>`);

        vidTitleWrapper.appendChild(downloadButton);

        downloadButton.onclick = e => {
            download(video, () => {
                GM_setValue(`downloaded(${video.vkey})`, true);
                downloadButton.onclick = null;
                unsafeWindow.downloadButton = downloadButton;
                let classes = downloadButton.querySelector('span').classList;
                classes.remove(iconClass);
                classes.add('ph-icon-cloud-done');
            });
        };
    });
}

let readyTimer = setInterval(() => {
    if (isReady()) {
        attachDownloader();

        clearInterval(readyTimer);
    }
}, 100);

console.log("ytDlpUrl:", ytDlpUrl);
