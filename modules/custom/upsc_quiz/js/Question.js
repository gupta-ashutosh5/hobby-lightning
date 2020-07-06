import React, {Component} from 'react';
import Answer from './Answer';
import parse from 'html-react-parser';

class Question extends Component{
  constructor(props) {
    super(props);
    this.state = this.getInitialState();

    this.getScoreValues = this.getScoreValues.bind(this);
  }

  getInitialState = () => ({
    question: this.props.question,
    qNo: this.props.qNo
  });

  getScoreValues(scores) {
    this.props.onOptionsSelect(scores);
  }

  componentWillMount() {
    this.setState(
      this.getInitialState(), function () {
        console.log('Question state cleared');
      }
    );
  }

  render() {
    return (
    <div>
      <div className = 'questionTitle'>{parse(this.state.question.question_title)}</div>
      <Answer
    answers={this.state.question.answers}
    qtitle={this.state.question.question_title}
    qNo={this.state.qNo}
    onOptionsSelect={this.getScoreValues}
    />
      <div className={this.props.showFeedback ? 'quiz-feedback-wrapper' : 'hidden'}>{parse(this.state.question.question_feedback)}</div>
    </div>
    );
  }
}

export default Question;
