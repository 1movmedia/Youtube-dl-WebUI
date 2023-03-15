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

async function download(video) {
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

    console.log("Response:", await response.body());
}

function attachDownloader() {
    document.querySelectorAll('div.container ul.full-row-thumbs.display-grid li.pcVideoListItem').forEach(li => {
        let phImage = li.querySelector('div.phimage');
        let phAnchor = phImage.querySelector('a');
        // let phImg = phAnchor.querySelector('img.thumb');
        // let phDuration = phAnchor.querySelector('var.duration');
        let channelAnchor = li.querySelector('.usernameWrap a');
        let channelIcon = li.querySelector('.usernameWrapper span[data-title]');
        // let viewsSpan = li.querySelector('span.views');
        // let ratingContainer = li.querySelector('.rating-container .value');
    
        let video = {
            // id: li.dataset.id,
            vkey: li.dataset.videoVkey,
            // segment: li.dataset.segment,
            // entryCode: li.dataset.entrycode,
            // title: phAnchor.dataset.title,
            url: phAnchor.href,
            // thumbnail: phImg.src,
            // alt: phImg.alt,
            // mediumthumb: phImg.dataset.mediumthumb,
            // mediabook: phImg.dataset.mediabook,
            // width: phImg.width,
            // height: phImg.height,
            // thumbs: phImg.dataset.thumbs,
            // thumbsPath: phImg.dataset.path,
            // duration: phDuration.innerText,
            uploaderUrl: channelAnchor.href,
            uploader: channelAnchor.innerText,
            uploaderType: channelIcon ? channelIcon.dataset.title : null,
            // views: viewsSpan.innerText,
            // rating: ratingContainer.innerText,
        };
    
        // console.log(video);

        unsafeWindow.li = li;

        let vidTitleWrapper = li.querySelector('div.vidTitleWrapper');

        let downloadButton = H(
            `<div class="rightAlign moreActionMenuButton">
                <span class="ph-icon-cloud-download"></span>
            </div>`);

        vidTitleWrapper.appendChild(downloadButton);

        downloadButton.onclick = e => {
            download(video);
        };
    });
}

let readyTimer = setInterval(() => {
    if (isReady()) {
        attachDownloader();

        clearInterval(readyTimer);
    }
}, 100);
