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

    // Copy fields not available via API
    [ "url", "userTitle", "userType", "userUrl", "cutFrom", "cutEnd" ].forEach(key => videoInfo[key] = video[key]);

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
            onclick: (e, btn) => {
                if ((video.cutFrom || video.cutEnd)) {
                    if (!video.cutFrom) {
                        alert('Begin Mark is not set');
                        return;
                    }
                    if (!video.cutEnd) {
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
                    setDownloaded();
                    console.log('button:', btn);
                    btn.buttonElement.onclick = null;
                    btn.iconElement.classList.remove(iconDownloadClass);
                    btn.iconElement.classList.add(iconDownloadedClass);
                });
            },
        },
        {
            iconClass: 'ph-icon-crop',
            caption: 'Mark Start',
            onclick: (e, btn) => {
                let videoElement = S('#player video');
                let time = videoElement.currentTime;
                let duration = videoElement.duration;
                video['cutFrom'] = time;
                video['duration'] = duration;
                btn.captionElement.innerText = `Mark Start (${time})`;
            },
        },
        {
            iconClass: 'ph-icon-crop',
            caption: 'Mark End',
            onclick: (e, btn) => {
                let videoElement = S('#player video');
                let time = videoElement.currentTime;
                let duration = videoElement.duration;
                video['cutEnd'] = duration - time;
                video['duration'] = duration;
                btn.captionElement.innerText = `Mark End (${time})`;
            },
        },
    ];

    let tabMenuWrapperRow = S('.video-actions-menu .tab-menu-wrapper-row');

    buttons.forEach(button => {
        let btnCell = H(`<div class="tab-menu-wrapper-cell"><div class="tab-menu-item"><i style="margin-right: 15px" class="${button.iconClass}"></i><span>${button.caption}</span></div></div>`);
        
        button.buttonElement = btnCell;
        button.iconElement = btnCell.querySelector('i');
        button.captionElement = btnCell.querySelector('span');
    
        tabMenuWrapperRow.appendChild(btnCell);
    
        btnCell.onclick = e => button.onclick(e, button);
    });

}

GM_registerMenuCommand('Clear >_dlp url', () => {
    ytDlpUrl = null;
    GM_setValue('ytDlpUrl', null);
    GM_deleteValue('ytDlpUrl');
})

console.log("ytDlpUrl:", ytDlpUrl);
