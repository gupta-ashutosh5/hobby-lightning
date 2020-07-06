import React, {Component} from 'react';

let scores = [];
class Answer extends Component{

  constructor(props) {
    super(props);
    this.state = this.getInitialState();
    this.handleOnChange = this.handleOnChange.bind(this);
  }

  getInitialState = () => ({
    answers: this.props.answers,
    qtitle: this.props.qtitle,
    qNo: this.props.qNo,
    scores: []
  });

  handleOnChange(e) {
    scores[e.target.dataset.qno] =  e.target.dataset.points;
    this.setState({
      scores: scores
    }, function(){
      this.props.onOptionsSelect(this.state.scores);
    });
  }

  componentWillMount() {
    scores = [];
    this.setState(
      this.getInitialState(), function () {
        console.log('Answer state cleared');
      }
    );
  }

  render() {
    return (
      <div>
        {this.state.answers.map((value, index) => {
          return (
            <div key={index}>
              <input type='radio'
            value={this.state.answers[index].choice}
            name={this.state.qtitle}
            data-correct={this.state.answers[index].correct}
            data-points={this.state.answers[index].points}
            data-qno={this.state.qNo}
            onChange={(e) => this.handleOnChange(e)}
            />{this.state.answers[index].choice}
            </div>
          )
        })}
      </div>
    );
  }

}

export default Answer;
