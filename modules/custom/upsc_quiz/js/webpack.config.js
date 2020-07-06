module.exports = {
  entry: ['whatwg-fetch', './Quiz.js'],
  output: {
    path: __dirname,
    filename: 'quiz.bundle.js'
  },
  module: {
    loaders: [
      {test: /\.js$/, exclude: /node_modules/, loader: 'babel-loader'},
      {test: /\.css$/, loader: "style-loader!css-loader"}
    ]
  }
};
