import Question from './Question';
import React, {Component} from 'react';
import ReactDOM from 'react-dom';
import CaptureUserModal from './CaptureUserModal';
import EmailAddressForm from './EmailAddressForm';
import UserRegistrationForm from './UserRegistrationForm';
import 'whatwg-fetch'; // https://www.npmjs.com/package/whatwg-fetch

class Quiz extends Component {

  constructor() {
    super();
    // we will need hard binding.
    // If it is not set, then when this function is assigned to a variable
    // this will point to global object.
    this.getData = this.getData.bind(this);
    this.state = this.getInitialState();

    this.handleClick = this.handleClick.bind(this);
    this.getScoreValues = this.getScoreValues.bind(this);
    this.startQuiz = this.startQuiz.bind(this);
    this.restartQuiz = this.restartQuiz.bind(this);
    this.viewAnswers = this.viewAnswers.bind(this);
    this.onChange = this.onChange.bind(this);
    this.toggleModal = this.toggleModal.bind(this);
    this.toggleLoginForm = this.toggleLoginForm.bind(this);
  }

  getInitialState = () => ({
    quiz: '',
    totalScore: 0,
    scores: [],
    showFeedback: false,
    startTime: 0,
    endTime: 0,
    startQuizCaptcha: '',
    isOpen: false,
    showLoginForm: true
  });

  getData() {
    var path = drupalSettings.path.currentPath;
    var nid = path.split('/')[1]; // This assumes the path is like node/123

    fetch('/upsc-quiz/data/' + nid, {
      method: 'GET',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/vnd.api+json'
      }
    }).then((response) => {
      if (response.ok) {
        response.json().then((data) => {
          this.setState({
            quiz: data.quiz
          });
        });
      } else {
        console.log('error getting data');
      }
    });
  }

  handleClick(e, uid) {
    if (uid === 0) {
      this.setState({
        isOpen: true
      }, function () {
        console.log(this.state.isOpen);
      });
    } else {
      var path = drupalSettings.path.currentPath;
      var nid = path.split('/')[1]; // This assumes the path is like node/123
      var totalScore = Math.round(Object.values(this.state.scores).reduce(
        (val1, val2) => parseFloat(val1) + parseFloat(val2), 0
      ) * 100) / 100;

      var timeSpent = Math.round((Date.now() - this.state.startTime) / 1000);

      fetch('/upsc-quiz/set-score/quiz/', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/vnd.api+json'
        },
        body: JSON.stringify({
          nid: nid,
          totalScore: totalScore,
          timeSpent: timeSpent
        })
      }).then((response) => {
        if (response.ok) {
          response.json().then((data) => {
            console.log(data.message);
            this.setState({
              totalScore: totalScore,
              isOpen: false
            });
          });
        } else {
          console.log('error sending data');
        }
      });
    }
  }

  getScoreValues(scores) {
    this.setState({
      scores: scores
    });
  }

  startQuiz(e) {
    e.preventDefault();
    this.getData();
    this.setState({
      startTime: Date.now()
    });
  }

  viewAnswers(e) {
    e.preventDefault();
    this.setState({
      showFeedback: true
    });
  }

  restartQuiz(e) {
    e.preventDefault();
    window.location.reload();
  }

  onChange() {
    this.setState({
      startQuizCaptcha: recaptchaRef.current.getValue()
    })
  }

  toggleModal() {
    this.setState({
      isOpen: !this.state.isOpen
    });
  }

  toggleLoginForm(e, showLogin) {
    this.setState({
      showLoginForm: showLogin
    });
  }

  render() {
    if (!this.state.quiz.questions) {
      return (
        <a onClick={this.startQuiz}>
          Start Quiz
        </a>
      )
    } else {
      return (
        <div>
          <form className='upsc-quiz-form'>
            {this.state.quiz.questions.map((value, index) => {
              return (
                <Question
                  question={this.state.quiz.questions[index]}
                  qNo={index}
                  onOptionsSelect={this.getScoreValues}
                  showFeedback={this.state.showFeedback}
                  key={index}/>
              )
            })}
            <input type="button" value="Submit Quiz" onClick={(e) => this.handleClick(e, drupalSettings.user.uid)}
                   className={(this.state.totalScore) ? 'hidden' : ''}/>
          </form>
          <div className={this.state.totalScore ? 'score-wrapper' : 'hidden'}>
            <span> Your Score: </span>
            <span>{this.state.totalScore}</span>
          </div>
          <div className={this.state.totalScore ? 'quiz-complete-links' : 'hidden'}>
            <span>
              <a onClick={this.viewAnswers}>View Answers</a>
            </span>
            <span>
              <a onClick={this.restartQuiz}>Restart Quiz</a>
            </span>
          </div>
          <CaptureUserModal show={this.state.isOpen}
                            onClose={this.toggleModal}>
            <EmailAddressForm onLoginSuccess={this.handleClick.bind(this)} showLoginForm={this.state.showLoginForm}
                              showRegister={this.toggleLoginForm}/>
            <UserRegistrationForm onLoginSuccess={this.handleClick.bind(this)} showLoginForm={this.state.showLoginForm}
                                  showLogin={this.toggleLoginForm}/>
          </CaptureUserModal>
        </div>
      );
    }
  }
}

ReactDOM.render(<Quiz/>, document.getElementById('upscquiz'));

