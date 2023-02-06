function sumFormatter(data) {
  var field = this.field
  return data.map(function (row) {
    let div = document.createElement("div");
      div.innerHTML = row[field];
    return +div.innerText.replace( /[^\d\.]*/g, '')
  }).reduce(function (sum, i) {
    return sum + i
  }, 0)
}
