import React, {Component} from "react";
import { useTable } from "react-table";

class UserScoreTable extends Component {
  constructor(props) {
    super(props);
  }

  render() {

    const columns = [
      {
        Header: "Quiz Leaderboard",
        columns: [
          {
            Header: "Rank",
            accessor: "rank"
          },
          {
            Header: "Name",
            accessor: "name"
          },
          {
            Header: "Score",
            accessor: "score"
          },
          {
            Header: "Time",
            accessor: "time"
          }
        ]
      }
    ];

    const Table = ({columns, data}) => {
      const {
        getTableProps,
        getTableBodyProps,
        headerGroups,
        rows,
        prepareRow
      } = useTable({
        columns,
        data
      });

      return (
        <table {...getTableProps()}>
          <thead>
          {headerGroups.map(headerGroup => (
            <tr {...headerGroup.getHeaderGroupProps()}>
              {headerGroup.headers.map(column => (
                <th {...column.getHeaderProps()}>{column.render("Header")}</th>
              ))}
            </tr>
          ))}
          </thead>
          <tbody {...getTableBodyProps()}>
          {rows.map((row, i) => {
            prepareRow(row);
            return (
              <tr {...row.getRowProps()}>
                {row.cells.map(cell => {
                  return <td {...cell.getCellProps()}>{cell.render("Cell")}</td>;
                })}
              </tr>
            );
          })}
          </tbody>
        </table>
      );
    };

    if (this.props.scoreData) {
      return (
        <div className={this.props.showLeaderBoard ? 'quiz-leader-board' : 'hidden'}>
          {<Table columns={columns} data={this.props.scoreData} />}
        </div>
    );
    }
    else {
      return (null);
    }

  }
}

export default UserScoreTable;
