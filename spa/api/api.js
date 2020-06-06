function fetchCollection(path) {
    // return fetch(ENV_API_ENDPOINT + path).then(resp => resp.json()).then(json => json['hydra:member']);
    return fetch("https://127.0.0.1:8000/" + path).then(resp => resp.json()).then(json => json['hydra:member']);
}
export function findConferences() {
    return fetchCollection('api/conferences');
}
export function findComments(conference) {
    return fetchCollection('api/comments?conference='+conference.id);
}