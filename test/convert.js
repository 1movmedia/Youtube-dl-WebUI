let data = [];

JSON.stringify(data.map(a => {
    let v = JSON.parse(a.details_json);
    v.url = a.url;
    return v;
}))
